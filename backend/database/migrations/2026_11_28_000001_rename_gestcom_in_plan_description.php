<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** L'application s'appelle désormais « Salloubhe Gescom » : corrige la description du plan d'essai déjà créée. */
    public function up(): void
    {
        DB::table('plans')
            ->where('description', 'like', '%GestCom%')
            ->update(['description' => DB::raw("REPLACE(description, 'GestCom', 'Salloubhe Gescom')")]);
    }

    public function down(): void {}
};
