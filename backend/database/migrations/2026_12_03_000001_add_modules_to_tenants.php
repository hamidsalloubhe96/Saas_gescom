<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Modules facultatifs choisis par l'entreprise (liste JSON). NULL = réglage par défaut du type d'activité. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->json('modules')->nullable());
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('modules'));
    }
};
