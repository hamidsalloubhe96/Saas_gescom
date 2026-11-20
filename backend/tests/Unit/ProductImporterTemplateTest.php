<?php

namespace Tests\Unit;

use App\Services\ProductImporter;
use PHPUnit\Framework\TestCase;

class ProductImporterTemplateTest extends TestCase
{
    public function test_le_modele_pharmacie_ajoute_les_colonnes_medicament(): void
    {
        $general = array_column(ProductImporter::templateColumns(false), 1);
        $pharmacy = array_column(ProductImporter::templateColumns(true), 1);

        $this->assertContains('name', $general);
        $this->assertContains('plu', $general);
        $this->assertNotContains('active_ingredient', $general);
        $this->assertContains('active_ingredient', $pharmacy);
        $this->assertContains('batch_number', $pharmacy);
        $this->assertContains('expires_at', $pharmacy);
    }

    public function test_le_modele_csv_est_lisible_par_excel(): void
    {
        $path = (new ProductImporter)->writeTemplate('csv', false);
        $content = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);            // BOM : Excel lit les accents
        $this->assertStringContainsString("Nom;Code-barres;Référence;Catégorie", $content); // séparateur « ; »
        $this->assertStringContainsString('Riz brisé 5 kg', $content);       // ligne d'exemple
    }

    public function test_le_modele_xlsx_est_un_fichier_valide(): void
    {
        $path = (new ProductImporter)->writeTemplate('xlsx', true);
        $zip = new \ZipArchive;

        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($path);
    }

    public function test_le_fichier_d_erreurs_liste_ligne_nom_et_probleme(): void
    {
        $path = (new ProductImporter)->writeErrors([
            ['line' => 5, 'name' => 'Lait', 'message' => 'Prix de vente invalide.'],
            ['line' => 6, 'name' => '', 'message' => 'Nom manquant.'],
        ]);
        $content = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringContainsString('Ligne;Nom;Problème', $content);
        $this->assertStringContainsString('5;Lait;', $content);
        $this->assertStringContainsString('Prix de vente invalide.', $content);
        $this->assertStringContainsString('Nom manquant.', $content);
    }
}
