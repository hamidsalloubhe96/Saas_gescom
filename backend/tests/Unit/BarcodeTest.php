<?php

namespace Tests\Unit;

use App\Services\Barcode;
use PHPUnit\Framework\TestCase;

class BarcodeTest extends TestCase
{
    public function test_cle_de_controle_des_formats_courants(): void
    {
        $this->assertSame(7, Barcode::checkDigit('590123412345'));   // EAN-13
        $this->assertSame(4, Barcode::checkDigit('9638507'));        // EAN-8
        $this->assertSame(2, Barcode::checkDigit('03600029145'));    // UPC-A
    }

    public function test_validation_des_codes(): void
    {
        $this->assertTrue(Barcode::isValid('5901234123457'));
        $this->assertTrue(Barcode::isValid('96385074'));
        $this->assertTrue(Barcode::isValid('036000291452'));
        $this->assertFalse(Barcode::isValid('5901234123458'), 'mauvaise clé');
        $this->assertFalse(Barcode::isValid('12345'), 'longueur non standard');
        $this->assertFalse(Barcode::isValid('ABC-123'), 'texte libre');
    }

    public function test_les_codes_internes_sont_des_ean13_valides_du_prefixe_200(): void
    {
        foreach ([1, 42, 999999999] as $n) {
            $body = '200'.str_pad((string) $n, 9, '0', STR_PAD_LEFT);
            $code = $body.Barcode::checkDigit($body);

            $this->assertSame(13, strlen($code));
            $this->assertStringStartsWith('200', $code);
            $this->assertTrue(Barcode::isValid($code));
        }
    }
}
