<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('author_name');
            $table->string('author_email')->nullable();
            $table->string('subject', 160);
            $table->string('status', 10)->default('open')->index(); // open | answered | closed
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('author_type', 6); // tenant | admin
            $table->string('author_name');
            $table->text('body');
            $table->timestamps();
        });

        // Relances déjà envoyées (une seule fois par entreprise, type et période).
        Schema::create('reminders_sent', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('kind', 30);
            $table->string('period_key', 40);
            $table->timestamp('sent_at');
            $table->unique(['tenant_id', 'kind', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders_sent');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
