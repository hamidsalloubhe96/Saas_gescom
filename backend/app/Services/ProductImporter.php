<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Options as CsvWriterOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Common\Entity\Row;

/**
 * Import de produits depuis un fichier Excel (.xlsx) ou CSV : lecture, contrôle ligne par ligne, puis enregistrement.
 * Les titres de colonnes sont reconnus sans tenir compte des accents, des majuscules ni des espaces.
 * Pour un produit déjà connu (même code-barres, sinon même référence), la ligne met le produit à jour : le stock n'est jamais modifié.
 */
class ProductImporter
{
    public const MAX_ROWS = 5000;

    /** Titres reconnus (formes normalisées) pour chaque champ. */
    private const ALIASES = [
        'name' => ['nom', 'designation', 'produit', 'libelle', 'name', 'nomduproduit', 'medicament', 'nomdumedicament'],
        'barcode' => ['codebarres', 'codebarre', 'ean', 'barcode', 'code', 'gencod'],
        'sku' => ['reference', 'ref', 'sku'],
        'category' => ['categorie', 'famille', 'category'],
        'unit' => ['unite', 'unit'],
        'purchase_price' => ['prixdachat', 'prixachat', 'achat', 'pa', 'purchaseprice'],
        'sale_price' => ['prixdevente', 'prixvente', 'vente', 'pv', 'prix', 'saleprice'],
        'tax_rate' => ['tva', 'tauxtva', 'taxe'],
        'stock' => ['stock', 'stockinitial', 'quantite', 'qte', 'qty'],
        'min_stock' => ['seuil', 'seuildalerte', 'seuilalerte', 'stockminimum', 'stockmin'],
        'active_ingredient' => ['principeactif', 'dci', 'activeingredient'],
        'dosage' => ['dosage'],
        'form' => ['forme', 'formegalenique'],
        'batch_number' => ['lot', 'numerodelot', 'nlot', 'nolot', 'batch'],
        'expires_at' => ['peremption', 'datedeperemption', 'expiration', 'dateexpiration', 'dateperemption'],
        'plu' => ['plu', 'codecourt', 'codecourtplu'],
        'sold_by_weight' => ['venduaupoids', 'aupoids', 'poids', 'soldbyweight'],
    ];

    /** Colonnes du modèle téléchargeable : [titre affiché, champ, exemple]. */
    public static function templateColumns(bool $pharmacy): array
    {
        $cols = [
            ['Nom', 'name', $pharmacy ? 'Doliprane 500 mg' : 'Riz brisé 5 kg'],
            ['Code-barres', 'barcode', '6001000000011'],
            ['Référence', 'sku', ''],
            ['Catégorie', 'category', $pharmacy ? 'Antalgiques' : 'Alimentaire'],
            ['Unité', 'unit', 'pièce'],
            ['Prix d\'achat', 'purchase_price', '800'],
            ['Prix de vente', 'sale_price', '1200'],
            ['TVA', 'tax_rate', '0'],
            ['Stock', 'stock', '30'],
            ['Seuil d\'alerte', 'min_stock', '10'],
            ['Vendu au poids', 'sold_by_weight', 'non'],
            ['Code court (PLU)', 'plu', ''],
        ];
        if ($pharmacy) {
            array_push($cols,
                ['Principe actif', 'active_ingredient', 'Paracétamol'],
                ['Dosage', 'dosage', '500 mg'],
                ['Forme', 'form', 'Comprimé'],
                ['N° de lot', 'batch_number', 'DL-2301'],
                ['Péremption', 'expires_at', '2027-12-31'],
            );
        }

        return $cols;
    }

    /** Écrit le modèle (une ligne d'exemple) dans un fichier temporaire et renvoie son chemin. */
    public function writeTemplate(string $format, bool $pharmacy): string
    {
        $cols = self::templateColumns($pharmacy);
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        if ($format === 'csv') {
            $writer = new CsvWriter(new CsvWriterOptions(FIELD_DELIMITER: ';', SHOULD_ADD_BOM: true));
        } else {
            $writer = new XlsxWriter;
        }
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_column($cols, 0)));
        $writer->addRow(Row::fromValues(array_column($cols, 2)));
        $writer->close();

        return $path;
    }

    /** Écrit un fichier CSV (séparateur « ; », lisible par Excel) : lignes en erreur à corriger puis réimporter. */
    public function writeErrors(array $errors): string
    {
        $path = tempnam(sys_get_temp_dir(), 'err');
        $writer = new CsvWriter(new CsvWriterOptions(FIELD_DELIMITER: ';', SHOULD_ADD_BOM: true));
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Ligne', 'Nom', 'Problème']));
        foreach ($errors as $e) {
            $writer->addRow(Row::fromValues([$e['line'], $e['name'] ?? '', $e['message']]));
        }
        $writer->close();

        return $path;
    }

    /**
     * Lit et contrôle le fichier sans rien enregistrer.
     *
     * @return array{rows: array<int, array>, summary: array, errors: array}
     */
    public function analyze(string $path, string $extension, string $onDuplicate): array
    {
        $raw = $this->read($path, $extension);
        if (count($raw) < 2) {
            abort(422, 'Le fichier est vide : ajoutez une ligne de titres puis vos produits.');
        }
        if (count($raw) - 1 > self::MAX_ROWS) {
            abort(422, 'Le fichier dépasse '.self::MAX_ROWS.' lignes. Découpez-le en plusieurs fichiers.');
        }

        $columns = $this->mapHeader(array_shift($raw));
        if (! isset($columns['name'])) {
            abort(422, 'Colonne « Nom » introuvable. Téléchargez le modèle pour voir les titres attendus.');
        }

        $existingBarcodes = Product::whereNotNull('barcode')->pluck('id', 'barcode')->all();
        $existingSkus = Product::whereNotNull('sku')->get(['id', 'sku'])->mapWithKeys(fn ($p) => [mb_strtolower($p->sku) => $p->id])->all();
        $extraBarcodes = ProductBarcode::pluck('barcode')->flip()->all();
        $existingPlus = Product::whereNotNull('plu')->pluck('id', 'plu')->all();
        $pharmacy = (tenant('business_type') ?? 'general') === 'pharmacy';

        $rows = [];
        $seenBarcodes = [];
        $seenPlus = [];
        $summary = ['read' => 0, 'new' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];

        foreach ($raw as $i => $cells) {
            $line = $i + 2;
            $data = $this->extract($cells, $columns);
            if ($this->isBlank($data)) {
                continue;
            }
            $summary['read']++;
            [$data, $error] = $this->normalize($data, $pharmacy);

            $productId = null;
            if (! $error && $data['barcode'] !== null) {
                if (isset($seenBarcodes[$data['barcode']])) {
                    $error = 'Code-barres en double dans le fichier (ligne '.$seenBarcodes[$data['barcode']].').';
                } elseif (isset($extraBarcodes[$data['barcode']])) {
                    $error = 'Ce code-barres est déjà un code supplémentaire d\'un autre produit.';
                } else {
                    $seenBarcodes[$data['barcode']] = $line;
                    $productId = $existingBarcodes[$data['barcode']] ?? null;
                }
            }
            if (! $error && $productId === null && $data['sku'] !== null) {
                $productId = $existingSkus[mb_strtolower($data['sku'])] ?? null;
            }
            if (! $error && $data['plu'] !== null) {
                $owner = $existingPlus[$data['plu']] ?? null;
                if (isset($seenPlus[$data['plu']])) {
                    $error = 'Code court (PLU) en double dans le fichier (ligne '.$seenPlus[$data['plu']].').';
                } elseif ($owner !== null && $owner !== $productId) {
                    $error = 'Ce code court (PLU) est déjà utilisé par un autre produit.';
                } else {
                    $seenPlus[$data['plu']] = $line;
                }
            }

            $status = $error ? 'error' : ($productId ? ($onDuplicate === 'skip' ? 'skip' : 'update') : 'new');
            $summary[$status]++;
            $rows[] = ['line' => $line, 'status' => $status, 'message' => $error, 'product_id' => $productId, 'data' => $data];
        }

        $errors = collect($rows)->where('status', 'error')->map(fn ($r) => ['line' => $r['line'], 'name' => $r['data']['name'] ?? '', 'message' => $r['message']])->values()->all();

        return ['rows' => $rows, 'summary' => $summary, 'errors' => $errors];
    }

    /**
     * Enregistre les lignes prêtes (nouvelles ou à mettre à jour). Tout ou rien : une erreur inattendue annule l'import.
     *
     * @return array{created: int, updated: int, skipped: int, failed: int, categories_created: int}
     */
    public function import(array $analysis, ?int $userId): array
    {
        $new = collect($analysis['rows'])->where('status', 'new');
        app(PlanLimits::class)->ensureCanAdd('products', $new->count());

        return DB::transaction(function () use ($analysis, $userId) {
            $stock = app(StockService::class);
            $categories = Category::get(['id', 'name'])->mapWithKeys(fn ($c) => [mb_strtolower($c->name) => $c->id])->all();
            $categoriesCreated = 0;
            $created = $updated = 0;
            $pharmacy = tenant()->hasModule('expiry');

            foreach ($analysis['rows'] as $row) {
                if (! in_array($row['status'], ['new', 'update'], true)) {
                    continue;
                }
                $d = $row['data'];
                $categoryId = null;
                if ($d['category'] !== null) {
                    $key = mb_strtolower($d['category']);
                    if (! isset($categories[$key])) {
                        $categories[$key] = Category::create(['name' => $d['category']])->id;
                        $categoriesCreated++;
                    }
                    $categoryId = $categories[$key];
                }

                // Seuls les champs renseignés dans le fichier sont écrits : une cellule vide ne remplace rien.
                $fields = array_filter([
                    'name' => $d['name'], 'barcode' => $d['barcode'], 'sku' => $d['sku'], 'category_id' => $categoryId,
                    'unit' => $d['unit'], 'purchase_price' => $d['purchase_price'], 'sale_price' => $d['sale_price'],
                    'tax_rate' => $d['tax_rate'], 'min_stock' => $d['min_stock'], 'plu' => $d['plu'],
                    'active_ingredient' => $d['active_ingredient'], 'dosage' => $d['dosage'], 'form' => $d['form'],
                ], fn ($v) => $v !== null);
                if ($d['sold_by_weight'] !== null) {
                    $fields['sold_by_weight'] = $d['sold_by_weight'];
                }

                if ($row['status'] === 'update') {
                    Product::whereKey($row['product_id'])->firstOrFail()->update($fields);
                    $updated++;

                    continue;
                }

                $fields += ['unit' => $d['sold_by_weight'] ? 'kg' : 'pièce', 'track_batches' => $pharmacy || $d['batch_number'] !== null];
                $product = Product::create($fields + ['purchase_price' => 0, 'sale_price' => 0, 'tax_rate' => 0, 'min_stock' => 0]);
                if (blank($product->sku)) {
                    $product->update(['sku' => sprintf('PRD-%05d', $product->id)]);
                }
                if (($d['stock'] ?? 0) > 0) {
                    $batch = $product->track_batches && $d['batch_number'] !== null
                        ? [['batch_number' => $d['batch_number'], 'expires_at' => $d['expires_at'], 'quantity' => $d['stock']]]
                        : null;
                    $stock->move($product->id, 'in', $d['stock'], 'Import de produits', $userId, batches: $batch);
                }
                $created++;
            }

            return [
                'created' => $created, 'updated' => $updated, 'skipped' => $analysis['summary']['skip'],
                'failed' => $analysis['summary']['error'], 'categories_created' => $categoriesCreated,
            ];
        });
    }

    // ---------------------------------------------------------------- lecture

    /** @return array<int, array<int, mixed>> toutes les lignes de la première feuille (titres compris) */
    private function read(string $path, string $extension): array
    {
        if ($extension === 'csv' || $extension === 'txt') {
            $content = (string) file_get_contents($path);
            $firstLine = strtok($content, "\n") ?: '';
            $reader = new CsvReader(new CsvOptions(
                FIELD_DELIMITER: substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',',
                ENCODING: mb_check_encoding($content, 'UTF-8') ? 'UTF-8' : 'CP1252',
            ));
        } else {
            $reader = new XlsxReader;
        }

        $out = [];
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $out[] = $row->toArray();
                    if (count($out) > self::MAX_ROWS + 50) {
                        break 2;
                    }
                }
                break;
            }
            $reader->close();
        } catch (\Throwable $e) {
            abort(422, 'Impossible de lire ce fichier. Utilisez un fichier .xlsx ou .csv, par exemple celui du modèle.');
        }

        return $out;
    }

    /** @return array<string, int> champ => index de colonne */
    private function mapHeader(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $title) {
            $key = $this->normalizeTitle((string) $title);
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($columns[$field]) && in_array($key, $aliases, true)) {
                    $columns[$field] = $index;
                }
            }
        }

        return $columns;
    }

    private function normalizeTitle(string $title): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(Str::ascii($title)));
    }

    private function extract(array $cells, array $columns): array
    {
        $data = [];
        foreach (array_keys(self::ALIASES) as $field) {
            $data[$field] = isset($columns[$field]) ? ($cells[$columns[$field]] ?? null) : null;
        }

        return $data;
    }

    private function isBlank(array $data): bool
    {
        return collect($data)->every(fn ($v) => $v === null || trim((string) ($v instanceof DateTimeInterface ? 'x' : $v)) === '');
    }

    // ---------------------------------------------------------------- contrôle d'une ligne

    /** @return array{0: array, 1: ?string} données normalisées et premier problème trouvé */
    private function normalize(array $raw, bool $pharmacy): array
    {
        $text = fn ($v) => ($v === null || $v instanceof DateTimeInterface || trim((string) $v) === '') ? null : trim((string) $v);
        $d = [];
        foreach (['name', 'barcode', 'sku', 'category', 'unit', 'active_ingredient', 'dosage', 'form', 'batch_number'] as $f) {
            $d[$f] = $text($raw[$f]);
        }
        $d += array_fill_keys(['purchase_price', 'sale_price', 'tax_rate', 'stock', 'min_stock', 'plu', 'sold_by_weight', 'expires_at'], null);

        if ($d['name'] === null) {
            return [$d, 'Nom manquant.'];
        }
        if (mb_strlen($d['name']) > 190) {
            return [$d, 'Nom trop long (190 caractères maximum).'];
        }
        // Un code-barres collé depuis Excel peut arriver en nombre (« 6.001E+12 ») : refusé plutôt que deviné.
        if ($d['barcode'] !== null && (mb_strlen($d['barcode']) > 60 || preg_match('/e\+/i', $d['barcode']))) {
            return [$d, 'Code-barres invalide : formatez la colonne en texte dans Excel.'];
        }

        foreach (['purchase_price' => 'Prix d\'achat', 'sale_price' => 'Prix de vente', 'tax_rate' => 'TVA', 'stock' => 'Stock', 'min_stock' => 'Seuil d\'alerte'] as $f => $label) {
            $v = $this->number($raw[$f]);
            if ($v === false) {
                return [$d, "$label invalide."];
            }
            if ($v !== null && ($v < 0 || ($f === 'tax_rate' && $v > 100))) {
                return [$d, "$label hors limites."];
            }
            $d[$f] = $v;
        }

        $plu = $text($raw['plu']);
        if ($plu !== null) {
            if (! preg_match('/^\d{1,5}$/', $plu)) {
                return [$d, 'Code court (PLU) invalide : 1 à 5 chiffres.'];
            }
            $d['plu'] = (string) ((int) $plu);
        }

        $weight = $text($raw['sold_by_weight']);
        if ($weight !== null) {
            $d['sold_by_weight'] = in_array(mb_strtolower($weight), ['oui', 'yes', 'o', 'y', '1', 'true', 'x', 'vrai'], true);
        }

        if ($d['batch_number'] !== null || $raw['expires_at'] !== null && $raw['expires_at'] !== '') {
            $date = $this->date($raw['expires_at']);
            if ($date === false) {
                return [$d, 'Date de péremption invalide (jour/mois/année ou année-mois-jour).'];
            }
            $d['expires_at'] = $date;
            if ($d['batch_number'] !== null && $date === null && ($d['stock'] ?? 0) > 0) {
                return [$d, 'Date de péremption manquante pour ce lot.'];
            }
            if ($d['batch_number'] === null && $date !== null && ($d['stock'] ?? 0) > 0) {
                return [$d, 'Numéro de lot manquant pour cette date de péremption.'];
            }
        }

        return [$d, null];
    }

    /** @return float|null|false null si vide, false si illisible */
    private function number(mixed $v): float|null|false
    {
        if ($v === null || (is_string($v) && trim($v) === '')) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = str_replace(["\u{00A0}", ' '], '', (string) $v);
        $s = str_contains($s, ',') && ! str_contains($s, '.') ? str_replace(',', '.', $s) : str_replace(',', '', $s);

        return is_numeric($s) ? (float) $s : false;
    }

    /** @return string|null|false date Y-m-d, null si vide, false si illisible */
    private function date(mixed $v): string|null|false
    {
        if ($v instanceof DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat('!'.$format, $s);
            if ($dt && $dt->format($format) === $s) {
                return $dt->format('Y-m-d');
            }
        }

        return false;
    }
}
