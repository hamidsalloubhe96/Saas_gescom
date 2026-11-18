<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Codes-barres : clé de contrôle (EAN-13, EAN-8, UPC-A) et génération de codes INTERNES.
 * Les codes internes commencent par « 200 », plage que la norme GS1 réserve à l'usage interne d'un magasin :
 * ils ne peuvent donc pas entrer en collision avec un code de fabricant.
 */
class Barcode
{
    private const INTERNAL_PREFIX = '200';

    /** Clé de contrôle des chiffres donnés (12 pour EAN-13, 7 pour EAN-8, 11 pour UPC-A). */
    public static function checkDigit(string $body): int
    {
        $sum = 0;
        $weight = 3; // le chiffre le plus à droite du corps pèse 3, puis 1, 3, 1…
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += (int) $body[$i] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - $sum % 10) % 10;
    }

    /** Vrai pour un EAN-13, EAN-8 ou UPC-A dont la clé est correcte. Les autres formats ne sont pas vérifiables. */
    public static function isValid(string $code): bool
    {
        if (! ctype_digit($code) || ! in_array(strlen($code), [8, 12, 13], true)) {
            return false;
        }

        return self::checkDigit(substr($code, 0, -1)) === (int) substr($code, -1);
    }

    /** Prochain code interne libre (EAN-13), séquentiel et sans doublon. À appeler dans une requête d'entreprise. */
    public function nextInternal(): string
    {
        return DB::transaction(function () {
            DB::table('document_sequences')->insertOrIgnore(['type' => 'barcode', 'year' => 0, 'last' => 0]);

            for ($tries = 0; $tries < 50; $tries++) {
                $row = DB::table('document_sequences')->where(['type' => 'barcode', 'year' => 0])->lockForUpdate()->first();
                $next = $row->last + 1;
                DB::table('document_sequences')->where('id', $row->id)->update(['last' => $next]);

                $body = self::INTERNAL_PREFIX.str_pad((string) $next, 9, '0', STR_PAD_LEFT);
                $code = $body.self::checkDigit($body);
                if (! Product::where('barcode', $code)->exists()) {
                    return $code;
                }
            }

            abort(500, 'Impossible de générer un code interne libre.');
        });
    }
}
