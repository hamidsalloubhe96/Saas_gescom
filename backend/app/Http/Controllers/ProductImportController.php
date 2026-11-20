<?php

namespace App\Http\Controllers;

use App\Services\ProductImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Import des produits par fichier : modèle à télécharger, contrôle sans enregistrement, puis import. */
class ProductImportController extends Controller
{
    public function __construct(private ProductImporter $importer) {}

    public function template(Request $request): BinaryFileResponse
    {
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = $this->importer->writeTemplate($format, (tenant('business_type') ?? 'general') === 'pharmacy');

        return response()->download($path, "modele-import-produits.$format")->deleteFileAfterSend(true);
    }

    /** Contrôle le fichier : rien n'est enregistré. */
    public function preview(Request $request): JsonResponse
    {
        $analysis = $this->analyze($request);

        return response()->json([
            'summary' => $analysis['summary'],
            'errors' => $analysis['errors'],
            'rows' => collect($analysis['rows'])->take(100)->map(fn ($r) => [
                'line' => $r['line'], 'status' => $r['status'], 'message' => $r['message'],
                'name' => $r['data']['name'], 'barcode' => $r['data']['barcode'], 'sale_price' => $r['data']['sale_price'], 'stock' => $r['data']['stock'],
            ])->values(),
        ]);
    }

    /** Enregistre les lignes prêtes ; les lignes en erreur sont ignorées et renvoyées. */
    public function import(Request $request): JsonResponse
    {
        $analysis = $this->analyze($request);
        $result = $this->importer->import($analysis, $request->user()->id);

        return response()->json($result + ['errors' => $analysis['errors']]);
    }

    /** Fichier d'erreurs à corriger puis réimporter (CSV). */
    public function errors(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'errors' => ['required', 'array', 'max:'.ProductImporter::MAX_ROWS],
            'errors.*.line' => ['required', 'integer'],
            'errors.*.name' => ['nullable', 'string', 'max:190'],
            'errors.*.message' => ['required', 'string', 'max:300'],
        ]);

        return response()->download($this->importer->writeErrors($data['errors']), 'produits-a-corriger.csv')->deleteFileAfterSend(true);
    }

    private function analyze(Request $request): array
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'on_duplicate' => ['nullable', 'in:update,skip'],
        ]);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($extension, ['xlsx', 'csv', 'txt'], true), 422, 'Format non pris en charge : utilisez un fichier .xlsx ou .csv.');

        return $this->importer->analyze($file->getRealPath(), $extension, $request->input('on_duplicate', 'update'));
    }
}
