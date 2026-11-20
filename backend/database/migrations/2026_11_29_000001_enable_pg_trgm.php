<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recherche rapide et tolérante aux fautes de frappe (extension PostgreSQL pg_trgm, fournie avec PostgreSQL).
 * L'extension vit dans le schéma public ; les entreprises (un schéma chacune) y accèdent en la préfixant.
 * Hors transaction : si l'hébergeur refuse l'extension, l'échec est ignoré et la recherche reste classique.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm SCHEMA public');
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function down(): void {}
};
