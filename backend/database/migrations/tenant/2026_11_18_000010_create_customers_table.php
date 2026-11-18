<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('tax_id', 60)->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
