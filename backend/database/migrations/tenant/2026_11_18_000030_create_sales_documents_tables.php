<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compteurs de numérotation : un par type de document et par année.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last')->default(0);
            $table->unique(['type', 'year']);
        });

        // Devis (quote) et factures (invoice) partagent la même structure.
        Schema::create('sales_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);                 // quote | invoice
            $table->string('number', 30)->nullable();   // facture : attribué à l'émission (numérotation sans trou)
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->date('issue_date');
            $table->date('due_date')->nullable();       // validité (devis) ou échéance (facture)
            $table->decimal('subtotal', 14, 2)->default(0);   // total HT
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);      // total TTC
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('source_quote_id')->nullable()->constrained('sales_documents')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'number']);
            $table->index(['type', 'status']);
        });

        Schema::create('sales_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('description');
            $table->string('unit', 20)->nullable();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2);       // HT après remise
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_document_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 20);               // cash, card, transfer, cheque, other
            $table->date('paid_at');
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sales_document_lines');
        Schema::dropIfExists('sales_documents');
        Schema::dropIfExists('document_sequences');
    }
};
