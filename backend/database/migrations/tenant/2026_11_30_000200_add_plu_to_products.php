<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Code court (PLU, 1 à 5 chiffres) des produits pesés : le numéro que la balance imprime dans le code-barres de l'étiquette. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('plu', 5)->nullable()->unique()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('plu'));
    }
};
