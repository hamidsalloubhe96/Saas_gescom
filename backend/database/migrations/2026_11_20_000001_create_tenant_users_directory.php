<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Annuaire central : copie (nom, e-mail, rôle, statut) des utilisateurs de TOUTES les entreprises,
        // pour les retrouver depuis la console sans parcourir chaque schéma. Jamais de mot de passe ici.
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('email')->index();
            $table->string('role', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
