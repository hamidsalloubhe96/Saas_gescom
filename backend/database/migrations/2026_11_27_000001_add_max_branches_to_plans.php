<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NULL = points de vente illimitées.
        Schema::table('plans', fn (Blueprint $table) => $table->unsignedInteger('max_branches')->nullable()->after('max_invoices_month'));
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('max_branches'));
    }
};
