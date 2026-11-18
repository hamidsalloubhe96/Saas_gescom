<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Codes-barres SUPPLÉMENTAIRES d'un produit (carton, autre fournisseur…). Le code principal reste products.barcode. */
    public function up(): void
    {
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('barcode', 60)->unique();
            $table->string('label', 60)->nullable();            // « Carton de 12 », « Fournisseur B »…
            $table->decimal('quantity', 14, 3)->default(1);     // unités du produit représentées par un scan de ce code
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcodes');
    }
};
