<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Numérotation séquentielle par type et par année (DEV-2026-0001, FAC-2026-0001, BC-2026-0001).
 * À appeler dans une transaction : le compteur est verrouillé jusqu'à son commit,
 * donc un rollback n'« use » pas de numéro (pas de trou).
 */
class NumberSequence
{
    private const PREFIX = ['quote' => 'DEV', 'invoice' => 'FAC', 'purchase' => 'BC', 'transfer' => 'TR'];

    public function next(string $type): string
    {
        $year = (int) now()->format('Y');

        DB::table('document_sequences')->insertOrIgnore(['type' => $type, 'year' => $year, 'last' => 0]);
        $row = DB::table('document_sequences')->where(['type' => $type, 'year' => $year])->lockForUpdate()->first();
        $next = $row->last + 1;
        DB::table('document_sequences')->where('id', $row->id)->update(['last' => $next]);

        return sprintf('%s-%d-%04d', self::PREFIX[$type], $year, $next);
    }
}
