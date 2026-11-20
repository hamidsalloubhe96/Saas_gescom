<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boulangerie : produits fabriqués à partir d'ingrédients (recette par fournée), production du jour
 * (les ingrédients sortent du stock, les produits finis y entrent) et bilan des invendus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_manufactured')->default(false)->after('track_batches');
            $table->decimal('batch_yield', 14, 3)->nullable()->after('is_manufactured'); // pièces obtenues par fournée
            $table->boolean('sellable')->default(true)->after('batch_yield');             // false : ingrédient, absent de la caisse
        });

        Schema::create('product_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();                      // produit fini
            $table->foreignId('ingredient_id')->constrained('products')->restrictOnDelete();       // ingrédient
            $table->decimal('quantity', 14, 3);                                                     // pour UNE fournée
            $table->unique(['product_id', 'ingredient_id']);
        });

        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->date('produced_on');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'produced_on']);
        });

        Schema::create('production_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);          // pièces produites
            $table->decimal('batches', 14, 3);           // fournées (peut être fractionnaire)
            $table->decimal('unit_cost', 14, 2)->default(0); // coût d'une pièce au moment de la production
        });

        Schema::create('daily_unsold', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('produced', 14, 3);
            $table->decimal('sold', 14, 3);
            $table->decimal('unsold', 14, 3);
            $table->decimal('discarded', 14, 3)->default(0); // mis au rebut (sorti du stock)
            $table->decimal('loss', 14, 2)->default(0);      // coût des pièces mises au rebut
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['day', 'branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_unsold');
        Schema::dropIfExists('production_lines');
        Schema::dropIfExists('productions');
        Schema::dropIfExists('product_recipe_items');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['is_manufactured', 'batch_yield', 'sellable']));
    }
};
