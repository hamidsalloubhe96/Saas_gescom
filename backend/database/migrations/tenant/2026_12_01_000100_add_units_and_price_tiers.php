<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conditionnements (carton de 12, palette…) et prix par palier de quantité.
 * Le stock reste compté dans l'unité de base du produit : un carton vendu retire « quantity » unités de base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->decimal('quantity', 14, 3);          // nombre d'unités de base contenues
            $table->decimal('sale_price', 14, 2);        // prix du conditionnement
            $table->string('barcode', 60)->nullable()->unique();
            $table->timestamps();
            $table->unique(['product_id', 'name']);
        });

        Schema::create('product_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_quantity', 14, 3);      // à partir de cette quantité (unité de base)
            $table->decimal('unit_price', 14, 2);
            $table->unique(['product_id', 'min_quantity']);
        });

        Schema::table('sales_document_lines', function (Blueprint $table) {
            // Unités de base par unité vendue (12 pour un carton de 12) : le stock sort de quantity × unit_factor.
            $table->decimal('unit_factor', 14, 3)->default(1)->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('sales_document_lines', fn (Blueprint $table) => $table->dropColumn('unit_factor'));
        Schema::dropIfExists('product_price_tiers');
        Schema::dropIfExists('product_units');
    }
};
