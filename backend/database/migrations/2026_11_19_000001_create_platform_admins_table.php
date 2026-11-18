<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comptes de l'ÉQUIPE de la plateforme (console). Totalement séparés des utilisateurs des entreprises.
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 20)->default('admin'); // super_admin | admin | support
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->text('two_factor_secret')->nullable();          // chiffré (cast "encrypted")
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Journal de TOUTES les actions de la console : qui, quoi, quand, sur quelle entreprise.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('admin_name')->nullable();   // copie : le nom reste lisible si le compte est supprimé
            $table->string('action', 60)->index();      // ex. tenant.suspend, auth.login
            $table->string('tenant_id')->nullable()->index();
            $table->string('description', 500);
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('platform_admins');
    }
};
