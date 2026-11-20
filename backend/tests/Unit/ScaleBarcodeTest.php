<?php

namespace Tests\Unit;

use App\Services\Barcode;
use App\Services\ScaleBarcode;
use PHPUnit\Framework\TestCase;

class ScaleBarcodeTest extends TestCase
{
    public function test_un_code_de_poids_est_construit_puis_relu(): void
    {
        $code = ScaleBarcode::forWeight(12, 750);

        $this->assertSame(13, strlen($code));
        $this->assertStringStartsWith('21000120', $code);
        $this->assertTrue(Barcode::isValid($code));
        $this->assertSame(['plu' => '00012', 'type' => ScaleBarcode::WEIGHT, 'value' => 750], ScaleBarcode::parse($code));
    }

    public function test_le_poids_vient_du_code_de_type_poids(): void
    {
        $parsed = ScaleBarcode::parse(ScaleBarcode::forWeight(5, 350));

        $this->assertSame(0.35, ScaleBarcode::weightKg($parsed, 6000));
    }

    public function test_le_poids_est_deduit_du_prix_pour_un_code_de_type_prix(): void
    {
        $body = '22'.'00005'.'02100';
        $parsed = ScaleBarcode::parse($body.Barcode::checkDigit($body));

        $this->assertSame(ScaleBarcode::PRICE, $parsed['type']);
        $this->assertSame(0.35, ScaleBarcode::weightKg($parsed, 6000)); // 2 100 à 6 000 le kg = 0,350 kg
        $this->assertSame(0.0, ScaleBarcode::weightKg($parsed, 0.0));
    }

    public function test_les_codes_qui_ne_sont_pas_de_balance_sont_refuses(): void
    {
        $this->assertNull(ScaleBarcode::parse('6001000000011'));          // code de fabricant
        $this->assertNull(ScaleBarcode::parse('2000000000017'));          // code interne (préfixe 200)
        $this->assertNull(ScaleBarcode::parse('2100012007509'));          // clé de contrôle fausse
        $this->assertNull(ScaleBarcode::parse('21000120075'));            // trop court
        $this->assertNull(ScaleBarcode::parse('abc'));
    }

    public function test_le_poids_est_plafonne_a_cinq_chiffres(): void
    {
        $this->assertSame('99999', substr(ScaleBarcode::forWeight(1, 250000), 7, 5));
    }
}
