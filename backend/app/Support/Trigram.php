<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/** Disponibilité de l'extension PostgreSQL pg_trgm (recherche tolérante aux fautes). Sans elle, la recherche reste classique. */
class Trigram
{
    private static ?bool $available = null;

    public static function available(): bool
    {
        return self::$available ??= self::detect();
    }

    public static function forget(): void
    {
        self::$available = null;
    }

    private static function detect(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }
        try {
            return (bool) DB::selectOne("select 1 as ok from pg_extension where extname = 'pg_trgm'");
        } catch (Throwable) {
            return false;
        }
    }
}
