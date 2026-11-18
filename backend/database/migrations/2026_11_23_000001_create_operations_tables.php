<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal des tâches planifiées (visible sur la page Santé).
        Schema::create('task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->index();
            $table->string('status', 10);                          // ok | failed
            $table->string('message', 500)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('ran_at')->useCurrent()->index();
        });

        // Rappels « à faire par vous » (test de restauration, maintenance) : date du dernier « fait ».
        Schema::create('maintenance_notes', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
        });

        // Textes légaux versionnés : chaque publication crée une version numérotée.
        Schema::create('legal_texts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->index();                    // terms | privacy | subscription | legal_notice
            $table->unsignedInteger('version')->nullable();        // NULL tant que c'est un brouillon
            $table->string('title');
            $table->longText('body');
            $table->string('status', 10)->default('draft');        // draft | published
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['key', 'version']);
        });

        // Cycle de vie des entreprises : suppression programmée (délai de grâce) et protection.
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('deletion_scheduled_at')->nullable();
            $table->boolean('is_protected')->default(false);
        });

        // Les quatre textes existent dès le départ en brouillon vide : à rédiger puis publier depuis la console.
        $now = now();
        foreach (['terms' => "Conditions d'utilisation", 'privacy' => 'Politique de confidentialité', 'subscription' => "Conditions d'abonnement", 'legal_notice' => 'Mentions légales'] as $key => $title) {
            DB::table('legal_texts')->insert(['key' => $key, 'version' => null, 'title' => $title, 'body' => '', 'status' => 'draft', 'created_at' => $now, 'updated_at' => $now]);
        }

        // Réglages ajoutés.
        foreach (['email_verification' => '1', 'deletion_grace_days' => '30', 'abandoned_days' => '90'] as $k => $v) {
            DB::table('platform_settings')->insertOrIgnore(['key' => $k, 'value' => $v, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['deletion_scheduled_at', 'is_protected']));
        Schema::dropIfExists('legal_texts');
        Schema::dropIfExists('maintenance_notes');
        Schema::dropIfExists('task_runs');
    }
};
