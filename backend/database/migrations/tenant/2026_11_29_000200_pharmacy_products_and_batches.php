<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pharmacie : champs médicament (principe actif, dosage, forme), suivi par lots avec date de péremption,
 * et index de recherche pg_trgm (si l'extension est disponible).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('active_ingredient', 190)->nullable()->after('name');
            $table->string('dosage', 60)->nullable()->after('active_ingredient');
            $table->string('form', 60)->nullable()->after('dosage');
            $table->boolean('track_batches')->default(false)->after('is_active');
            $table->index('active_ingredient');
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 60);
            $table->date('expires_at')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'branch_id', 'batch_number']);
            $table->index(['branch_id', 'expires_at']);
        });

        Schema::table('stock_movements', fn (Blueprint $table) => $table->json('batches')->nullable());
        Schema::table('stock_transfer_lines', fn (Blueprint $table) => $table->json('batches')->nullable());

        $this->trigramIndexes();
    }

    private function trigramIndexes(): void
    {
        try {
            if (! DB::selectOne("select 1 as ok from pg_extension where extname = 'pg_trgm'")) {
                return;
            }
            DB::statement('CREATE INDEX IF NOT EXISTS products_name_trgm ON products USING gin (name public.gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS products_active_ingredient_trgm ON products USING gin (active_ingredient public.gin_trgm_ops)');
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        Schema::table('stock_transfer_lines', fn (Blueprint $table) => $table->dropColumn('batches'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropColumn('batches'));
        Schema::dropIfExists('product_batches');
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['active_ingredient']);
            $table->dropColumn(['active_ingredient', 'dosage', 'form', 'track_batches']);
        });
    }
};
