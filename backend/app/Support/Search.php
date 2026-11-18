<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Recherche « contient » insensible à la casse et portable (PostgreSQL en production, SQLite dans les tests).
 * Les caractères spéciaux (% _ \) de la saisie sont neutralisés : ils sont cherchés littéralement.
 */
class Search
{
    /**
     * @param  array<int, string>  $columns  colonnes SQL, ou « json:colonne.cle » pour une clé d'une colonne JSON
     */
    public static function contains($query, array $columns, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $driver = $query->getConnection()->getDriverName();

        return $query->where(function ($w) use ($columns, $like, $driver) {
            foreach ($columns as $column) {
                $expr = self::expression($column, $driver);
                if ($driver === 'pgsql') {
                    $w->orWhereRaw("{$expr} ilike ?", [$like]);
                } else {
                    $w->orWhereRaw("lower({$expr}) like lower(?) escape '\\'", [$like]);
                }
            }
        });
    }

    private static function expression(string $column, string $driver): string
    {
        if (! str_starts_with($column, 'json:')) {
            return $column;
        }

        [$col, $key] = explode('.', substr($column, 5), 2);

        return $driver === 'pgsql' ? "{$col}->>'{$key}'" : "json_extract({$col}, '\$.{$key}')";
    }
}
