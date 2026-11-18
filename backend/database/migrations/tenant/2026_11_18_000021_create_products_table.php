<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 60)->nullable()->unique(); // généré automatiquement si vide
            $table->string('barcode', 60)->nullable()->unique();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit', 20)->default('pièce');
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('sale_price', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            // Quantité en stock : maintenue uniquement via les mouvements (stock_movements).
            $table->decimal('stock_quantity', 14, 3)->default(0);
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
