<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Points de vente : l'entreprise a un siège (is_main) et autant de points de vente qu'elle veut.
 * Chaque point de vente a son stock (stock_levels), ses ventes, sa caisse et ses prix locaux.
 * products.stock_quantity devient le TOTAL toutes points de vente ; le détail est dans stock_levels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 20)->unique();
            $table->string('region', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        // Un seul siège, garanti par la base.
        DB::statement('CREATE UNIQUE INDEX branches_single_main ON branches ((1)) WHERE is_main');

        $now = now();
        $mainId = DB::table('branches')->insertGetId(['name' => 'Siège', 'code' => 'SIEGE', 'is_main' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->unique(['product_id', 'branch_id']);
        });
        // Le stock existant est celui du siège.
        DB::statement('INSERT INTO stock_levels (product_id, branch_id, quantity) SELECT id, ?, stock_quantity FROM products', [$mainId]);

        Schema::create('branch_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('sale_price', 14, 2);
            $table->unique(['product_id', 'branch_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('type', 20)->change(); // + transfer_in / transfer_out
            $table->foreignId('branch_id')->nullable()->after('product_id')->constrained()->restrictOnDelete();
        });
        DB::table('stock_movements')->update(['branch_id' => $mainId]);

        foreach (['sales_documents', 'payments', 'register_sessions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
                $table->index('branch_id');
            });
            DB::table($name)->update(['branch_id' => $mainId]);
        }

        // Une caisse ouverte à la fois PAR point de vente (et non plus une seule pour toute l'entreprise).
        DB::statement('DROP INDEX IF EXISTS register_sessions_single_open');
        DB::statement('CREATE UNIQUE INDEX register_sessions_single_open ON register_sessions (branch_id) WHERE closed_at IS NULL');

        Schema::table('users', function (Blueprint $table) {
            // null = accès à tous les points de vente (siège) ; sinon l'utilisateur est limité à son point de vente.
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('status', 12)->default('requested')->index(); // requested | shipped | received | cancelled
            $table->text('note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);            // demandée / expédiée
            $table->decimal('received_quantity', 14, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('branch_id'));
        DB::statement('DROP INDEX IF EXISTS register_sessions_single_open');
        DB::statement('CREATE UNIQUE INDEX register_sessions_single_open ON register_sessions ((1)) WHERE closed_at IS NULL');
        foreach (['register_sessions', 'payments', 'sales_documents', 'stock_movements'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropConstrainedForeignId('branch_id'));
        }
        Schema::dropIfExists('branch_prices');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('branches');
    }
};
