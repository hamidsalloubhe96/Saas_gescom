<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vraies colonnes (filtrables) à la place de la colonne JSON "data" : voir Tenant::getCustomColumns().
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->index(); // active | suspended | banned
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspended_reason', 255)->nullable();
            $table->string('owner_email')->nullable();
            $table->string('country', 60)->nullable();
            $table->string('city', 100)->nullable();
            $table->timestamp('last_activity_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['status', 'suspended_at', 'suspended_reason', 'owner_email', 'country', 'city', 'last_activity_at']);
        });
    }
};
