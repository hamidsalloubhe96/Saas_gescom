<?php

namespace App\Services;

/**
 * Code-barres de balance (EAN-13) : « 2 T PPPPP VVVVV C ».
 *  - T = 1 : la valeur est le poids en grammes ; T = 2 : la valeur est le prix à payer ;
 *  - PPPPP : code court (PLU) du produit, 5 chiffres ; VVVVV : valeur sur 5 chiffres ; C : clé de contrôle EAN.
 * Les codes internes de l'application commencent par « 200 » (T = 0) : aucune confusion possible.
 */
class ScaleBarcode
{
    public const WEIGHT = 1;

    public const PRICE = 2;

    /** @return array{plu: string, type: int, value: int}|null null si ce n'est pas un code de balance valide */
    public static function parse(string $code): ?array
    {
        if (! preg_match('/^2([12])(\d{5})(\d{5})(\d)$/', $code, $m) || ! Barcode::isValid($code)) {
            return null;
        }

        return ['plu' => $m[2], 'type' => (int) $m[1], 'value' => (int) $m[3]];
    }

    /** Code complet pour un poids en grammes (utilisé pour imprimer l'étiquette d'un colis pesé). */
    public static function forWeight(string|int $plu, int $grams): string
    {
        $body = '21'.str_pad((string) $plu, 5, '0', STR_PAD_LEFT).str_pad((string) min($grams, 99999), 5, '0', STR_PAD_LEFT);

        return $body.Barcode::checkDigit($body);
    }

    /** Poids en kg d'un code de balance : direct (type poids) ou déduit du prix au kilo (type prix). */
    public static function weightKg(array $parsed, float $pricePerKg): float
    {
        if ($parsed['type'] === self::WEIGHT) {
            return round($parsed['value'] / 1000, 3);
        }

        return $pricePerKg > 0 ? round($parsed['value'] / $pricePerKg, 3) : 0.0;
    }
}
