<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft'); // draft, ordered, partial, received, cancelled
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);  // total HT
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);     // total TTC
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('description');
            $table->string('unit', 20)->nullable();
            $table->decimal('quantity', 14, 3);
            $table->decimal('quantity_received', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2);
        });

        // Règlements faits aux fournisseurs.
        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 20);
            $table->date('paid_at');
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
