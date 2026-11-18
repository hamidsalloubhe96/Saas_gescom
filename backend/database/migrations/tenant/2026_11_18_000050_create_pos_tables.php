<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une session = une période d'ouverture de la caisse (fond de caisse, ventes, comptage à la clôture).
        Schema::create('register_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('opening_amount', 14, 2)->default(0);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 14, 2)->nullable(); // fond + espèces encaissées
            $table->decimal('counted_cash', 14, 2)->nullable();  // espèces comptées à la clôture
            $table->decimal('difference', 14, 2)->nullable();    // compté - attendu
            $table->decimal('total_sales', 14, 2)->nullable();   // photo du CA de la session à la clôture
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Une seule caisse ouverte à la fois, garanti par la base (index unique partiel PostgreSQL).
        DB::statement('CREATE UNIQUE INDEX register_sessions_single_open ON register_sessions ((1)) WHERE closed_at IS NULL');

        Schema::table('sales_documents', function (Blueprint $table) {
            $table->string('channel', 20)->default('back_office')->after('type'); // back_office | pos
            $table->foreignId('register_session_id')->nullable()->after('channel')->constrained('register_sessions')->nullOnDelete();
            $table->index(['channel', 'register_session_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('register_session_id')->nullable()->after('sales_document_id')->constrained('register_sessions')->nullOnDelete();
        });

        // Client anonyme utilisé pour les ventes comptoir sans fiche client.
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_walk_in')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('is_walk_in'));
        Schema::table('payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('register_session_id');
        });
        Schema::table('sales_documents', function (Blueprint $t) {
            $t->dropIndex(['channel', 'register_session_id']);
            $t->dropConstrainedForeignId('register_session_id');
            $t->dropColumn('channel');
        });
        DB::statement('DROP INDEX IF EXISTS register_sessions_single_open');
        Schema::dropIfExists('register_sessions');
    }
};
