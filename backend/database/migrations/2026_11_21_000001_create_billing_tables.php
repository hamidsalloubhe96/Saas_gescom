<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Réglages de la plateforme (durée d'essai, jours de grâce, inscriptions, maintenance…).
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Forfaits vendus. Une limite NULL = illimité.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('price', 14, 2)->default(0);          // prix par période
            $table->string('currency', 8)->default('XOF');
            $table->unsignedSmallInteger('period_months')->default(1);
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_products')->nullable();
            $table->unsignedInteger('max_invoices_month')->nullable();
            $table->json('tiers')->nullable();                     // paliers payants : [{"up_to": 10, "price": 5000}, ...]
            $table->boolean('is_trial')->default(false);
            $table->unsignedSmallInteger('trial_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Abonnement courant d'une entreprise (un seul par entreprise).
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 20)->index();                 // trial | active | past_due | expired | cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable()->index(); // prochaine échéance
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('custom_limits')->nullable();             // limites personnalisées pour cette entreprise
            $table->timestamp('limits_unlocked_until')->nullable(); // déblocage temporaire des limites
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('XOF');
            $table->unsignedSmallInteger('months')->default(1);    // nombre de périodes payées
            $table->string('method', 20);                          // cash | transfer | mobile_money | card | other
            $table->string('reference')->nullable();
            $table->string('status', 12)->default('pending')->index(); // pending | validated | rejected
            $table->string('source', 12)->default('tenant');       // tenant | console | webhook
            $table->date('paid_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Qui a suspendu l'entreprise : la console (manuel) ou l'abonnement (automatique, levé par un paiement).
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('suspension_source', 12)->nullable();
        });

        // Forfaits de départ : valeurs d'exemple à ajuster dans la console (Forfaits).
        $now = now();
        DB::table('plans')->insert([
            ['slug' => 'essai', 'name' => 'Essai gratuit', 'description' => 'Pour découvrir Salloubhe Gescom', 'price' => 0, 'currency' => 'XOF', 'period_months' => 1,
                'max_users' => 3, 'max_products' => 100, 'max_invoices_month' => 50, 'is_trial' => true, 'trial_days' => 14, 'is_active' => true, 'sort' => 0],
            ['slug' => 'petite', 'name' => 'Petite entreprise', 'description' => 'Pour démarrer', 'price' => 10000, 'currency' => 'XOF', 'period_months' => 1,
                'max_users' => 3, 'max_products' => 300, 'max_invoices_month' => 200, 'is_trial' => false, 'trial_days' => null, 'is_active' => true, 'sort' => 1],
            ['slug' => 'standard', 'name' => 'Entreprise', 'description' => 'Pour une équipe', 'price' => 25000, 'currency' => 'XOF', 'period_months' => 1,
                'max_users' => 10, 'max_products' => 2000, 'max_invoices_month' => 1000, 'is_trial' => false, 'trial_days' => null, 'is_active' => true, 'sort' => 2],
            ['slug' => 'pro', 'name' => 'Grande entreprise', 'description' => 'Sans limite de produits ni de factures', 'price' => 50000, 'currency' => 'XOF', 'period_months' => 1,
                'max_users' => 30, 'max_products' => null, 'max_invoices_month' => null, 'is_trial' => false, 'trial_days' => null, 'is_active' => true, 'sort' => 3],
        ] + []);
        DB::table('plans')->update(['created_at' => $now, 'updated_at' => $now]);

        // Réglages par défaut.
        foreach (['trial_days' => '14', 'grace_days' => '7', 'currency' => 'XOF', 'signup_open' => '1', 'maintenance' => '0',
            'payment_instructions' => "Virement ou mobile money : indiquez la référence de votre paiement ci-dessous, nous le validons sous 24 h ouvrées."] as $k => $v) {
            DB::table('platform_settings')->insert(['key' => $k, 'value' => $v, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Les entreprises déjà créées démarrent en essai (durée complète, à partir d'aujourd'hui).
        $trial = DB::table('plans')->where('slug', 'essai')->first();
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('subscriptions')->insert([
                'tenant_id' => $tenantId, 'plan_id' => $trial->id, 'status' => 'trial',
                'trial_ends_at' => $now->copy()->addDays($trial->trial_days), 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn('suspension_source'));
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('platform_settings');
    }
};
