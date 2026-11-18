<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Vrai quand la plateforme a généré un mot de passe provisoire : à changer à la prochaine connexion.
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('must_change_password'));
    }
};
