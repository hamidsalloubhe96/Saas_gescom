<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Demandes d'inscription reçues depuis le site public (prospects à contacter).
        Schema::create('signup_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone', 40)->nullable();
            $table->string('company');
            $table->string('country', 60)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 12)->default('new')->index(); // new | contacted | converted | rejected
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('tenant_id')->nullable();               // entreprise créée à partir de la demande
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        // Avis laissés par les utilisateurs ; relus dans la console avant publication.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('author_name');
            $table->string('company')->nullable();
            $table->unsignedTinyInteger('rating');                 // 1 à 5
            $table->text('body');
            $table->string('status', 12)->default('pending')->index(); // pending | published | hidden
            $table->timestamp('published_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
        });

        // Messages affichés dans toutes les entreprises (nouveautés, maintenance prévue…).
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('level', 10)->default('info');          // info | warning | update
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('signup_requests');
    }
};
