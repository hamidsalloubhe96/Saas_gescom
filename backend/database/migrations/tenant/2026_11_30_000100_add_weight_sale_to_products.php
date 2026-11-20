<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vente au poids : le prix est au kilo (ou au litre) et la quantité vendue est un poids avec trois décimales. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('sold_by_weight')->default(false)->after('unit');
            $table->decimal('min_sale_quantity', 14, 3)->default(0)->after('sold_by_weight');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['sold_by_weight', 'min_sale_quantity']));
    }
};
