<?php

use App\Http\Controllers\Console\AccountController;
use App\Http\Controllers\Console\AuditController;
use App\Http\Controllers\Console\AuthController;
use App\Http\Controllers\Console\DashboardController;
use App\Http\Controllers\Console\AnnouncementController;
use App\Http\Controllers\Console\DueController;
use App\Http\Controllers\Console\HealthController;
use App\Http\Controllers\Console\LegalController;
use App\Http\Controllers\Console\TenantLifecycleController;
use App\Http\Controllers\Console\RequestController;
use App\Http\Controllers\Console\ReviewController;
use App\Http\Controllers\Console\PaymentController;
use App\Http\Controllers\Console\PlanController;
use App\Http\Controllers\Console\QuotaController;
use App\Http\Controllers\Console\SettingsController;
use App\Http\Controllers\Console\TenantBillingController;
use App\Http\Controllers\Console\TeamController;
use App\Http\Controllers\Console\TenantController;
use App\Http\Controllers\Console\UserDirectoryController;
use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Console de la plateforme (équipe GestCom) : pages Blade, session séparée (garde "admin").
| Totalement indépendante de l'application React et des utilisateurs des entreprises.
|--------------------------------------------------------------------------
*/
Route::prefix('console')->name('console.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:console-login')->name('login.submit');
        Route::get('mot-de-passe-oublie', [\App\Http\Controllers\Console\PasswordResetController::class, 'showForgot'])->name('forgot');
        Route::post('mot-de-passe-oublie', [\App\Http\Controllers\Console\PasswordResetController::class, 'sendCode'])->middleware(['throttle:5,1,cforgot_m', 'throttle:20,60,cforgot_h'])->name('forgot.send');
        Route::get('reinitialisation', [\App\Http\Controllers\Console\PasswordResetController::class, 'showReset'])->name('reset');
        Route::post('reinitialisation', [\App\Http\Controllers\Console\PasswordResetController::class, 'reset'])->middleware('throttle:10,1')->name('reset.submit');
        Route::get('verification', [AuthController::class, 'showTwoFactor'])->name('twofactor');
        Route::post('verification', [AuthController::class, 'verifyTwoFactor'])->middleware('throttle:10,1')->name('twofactor.verify');
    });

    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // AuthenticateSession : changer le mot de passe d'un compte ferme ses autres sessions ouvertes.
    Route::middleware(['auth:admin', \Illuminate\Session\Middleware\AuthenticateSession::class, 'console.security'])->group(function () {
        Route::redirect('/', '/console/dashboard');
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Mon compte
        Route::get('mot-de-passe', [AccountController::class, 'showPassword'])->name('password');
        Route::put('mon-compte/email', [AccountController::class, 'updateEmail'])->middleware('throttle:10,1')->name('account.email');
        Route::put('mot-de-passe', [AccountController::class, 'updatePassword'])->middleware('throttle:10,1')->name('password.update');
        Route::get('double-authentification', [AccountController::class, 'showTwoFactorSetup'])->name('twofactor.setup');
        Route::post('double-authentification', [AccountController::class, 'confirmTwoFactor'])->middleware('throttle:10,1')->name('twofactor.setup.confirm');
        Route::delete('double-authentification', [AccountController::class, 'disableTwoFactor'])->middleware('throttle:5,1')->name('twofactor.setup.disable');

        // Entreprises
        Route::middleware('console.can:tenants.view')->group(function () {
            Route::get('entreprises', [TenantController::class, 'index'])->name('tenants.index');
        });
        Route::middleware('console.can:tenants.create')->group(function () {
            Route::get('entreprises/nouvelle', [TenantController::class, 'create'])->name('tenants.create');
            Route::post('entreprises', [TenantController::class, 'store'])->middleware('throttle:20,1')->name('tenants.store');
        });
        Route::get('entreprises/{tenant}', [TenantController::class, 'show'])->middleware('console.can:tenants.view')->name('tenants.show');
        Route::middleware('console.can:tenants.update')->group(function () {
            Route::get('entreprises/{tenant}/modifier', [TenantController::class, 'edit'])->name('tenants.edit');
            Route::put('entreprises/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        });

        // Utilisateurs de toutes les entreprises (annuaire central) et assistance
        Route::get('utilisateurs', [UserDirectoryController::class, 'index'])->middleware('console.can:users.view')->name('users.index');
        Route::middleware('console.can:users.manage')->group(function () {
            Route::post('utilisateurs/{entry}/activer', [UserDirectoryController::class, 'toggleActive'])->middleware('throttle:30,1')->name('users.toggle');
            Route::post('utilisateurs/{entry}/deconnecter', [UserDirectoryController::class, 'forceLogout'])->middleware('throttle:30,1')->name('users.logout');
            Route::post('utilisateurs/{entry}/mot-de-passe', [UserDirectoryController::class, 'resetPassword'])->middleware('throttle:20,1')->name('users.reset');
        });
        Route::post('utilisateurs/{entry}/assistance', [UserDirectoryController::class, 'impersonate'])
            ->middleware(['console.can:users.impersonate', 'throttle:20,1'])->name('users.impersonate');
        Route::middleware('console.can:tenants.suspend')->group(function () {
            Route::post('entreprises/{tenant}/suspendre', [TenantController::class, 'suspend'])->middleware('throttle:20,1')->name('tenants.suspend');
            Route::post('entreprises/{tenant}/retablir', [TenantController::class, 'reactivate'])->middleware('throttle:20,1')->name('tenants.reactivate');
        });
        Route::post('entreprises/{tenant}/bannir', [TenantController::class, 'ban'])
            ->middleware(['console.can:tenants.ban', 'throttle:10,1'])->name('tenants.ban');

        // Abonnements d'une entreprise (depuis sa fiche)
        Route::middleware('console.can:subscriptions.manage')->prefix('entreprises/{tenant}/abonnement')->name('tenants.billing.')->group(function () {
            Route::post('activer', [TenantBillingController::class, 'activate'])->middleware('throttle:20,1')->name('activate');
            Route::post('jours-offerts', [TenantBillingController::class, 'offerDays'])->middleware('throttle:20,1')->name('offer');
            Route::post('forfait', [TenantBillingController::class, 'changePlan'])->middleware('throttle:20,1')->name('plan');
            Route::post('resilier', [TenantBillingController::class, 'cancel'])->middleware('throttle:10,1')->name('cancel');
        });
        Route::post('entreprises/{tenant}/abonnement/paiement', [TenantBillingController::class, 'recordPayment'])
            ->middleware(['console.can:payments.validate', 'throttle:20,1'])->name('tenants.billing.payment');

        // Forfaits, échéances, paiements, limites
        Route::get('forfaits', [PlanController::class, 'index'])->middleware('console.can:plans.view')->name('plans.index');
        Route::middleware('console.can:plans.manage')->group(function () {
            Route::get('forfaits/nouveau', [PlanController::class, 'create'])->name('plans.create');
            Route::post('forfaits', [PlanController::class, 'store'])->middleware('throttle:20,1')->name('plans.store');
            Route::get('forfaits/{plan}/modifier', [PlanController::class, 'edit'])->name('plans.edit');
            Route::put('forfaits/{plan}', [PlanController::class, 'update'])->middleware('throttle:30,1')->name('plans.update');
            Route::patch('forfaits/{plan}/actif', [PlanController::class, 'toggle'])->middleware('throttle:30,1')->name('plans.toggle');
        });
        Route::get('echeances', [DueController::class, 'index'])->middleware('console.can:tenants.view')->name('dues.index');
        Route::middleware('console.can:payments.validate')->group(function () {
            Route::get('paiements', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('paiements/{payment}/recu', [PaymentController::class, 'receipt'])->name('payments.receipt');
            Route::post('paiements/{payment}/valider', [PaymentController::class, 'validatePayment'])->middleware('throttle:30,1')->name('payments.validate');
            Route::post('paiements/{payment}/refuser', [PaymentController::class, 'reject'])->middleware('throttle:30,1')->name('payments.reject');
        });
        Route::get('limites', [QuotaController::class, 'index'])->middleware('console.can:quotas.view')->name('quotas.index');
        Route::middleware('console.can:quotas.manage')->group(function () {
            Route::put('limites/{tenant}', [QuotaController::class, 'update'])->middleware('throttle:30,1')->name('quotas.update');
            Route::post('limites/{tenant}/debloquer', [QuotaController::class, 'unlock'])->middleware('throttle:30,1')->name('quotas.unlock');
            Route::post('limites/{tenant}/rebloquer', [QuotaController::class, 'relock'])->middleware('throttle:30,1')->name('quotas.relock');
        });

        // Demandes d'inscription, avis, annonces
        Route::middleware('console.can:requests.manage')->group(function () {
            Route::get('demandes', [RequestController::class, 'index'])->name('requests.index');
            Route::patch('demandes/{signupRequest}', [RequestController::class, 'update'])->middleware('throttle:60,1')->name('requests.update');
        });
        Route::middleware('console.can:tickets.manage')->group(function () {
            Route::get('support', [\App\Http\Controllers\Console\TicketController::class, 'index'])->name('tickets.index');
            Route::get('support/{ticket}', [\App\Http\Controllers\Console\TicketController::class, 'show'])->name('tickets.show');
            Route::post('support/{ticket}/repondre', [\App\Http\Controllers\Console\TicketController::class, 'reply'])->middleware('throttle:30,1')->name('tickets.reply');
            Route::patch('support/{ticket}/statut', [\App\Http\Controllers\Console\TicketController::class, 'status'])->middleware('throttle:30,1')->name('tickets.status');
        });

        Route::middleware('console.can:reviews.manage')->group(function () {
            Route::get('avis', [ReviewController::class, 'index'])->name('reviews.index');
            Route::patch('avis/{review}', [ReviewController::class, 'update'])->middleware('throttle:60,1')->name('reviews.update');
        });
        Route::middleware('console.can:announcements.manage')->prefix('annonces')->name('announcements.')->group(function () {
            Route::get('/', [AnnouncementController::class, 'index'])->name('index');
            Route::post('/', [AnnouncementController::class, 'store'])->middleware('throttle:20,1')->name('store');
            Route::patch('{announcement}/actif', [AnnouncementController::class, 'toggle'])->name('toggle');
            Route::delete('{announcement}', [AnnouncementController::class, 'destroy'])->name('destroy');
        });

        // Fin de vie d'une entreprise : suppression programmée, protection, export (super administrateur)
        Route::middleware('console.can:tenants.delete')->prefix('entreprises/{tenant}')->name('tenants.lifecycle.')->group(function () {
            Route::post('suppression', [TenantLifecycleController::class, 'schedule'])->middleware('throttle:5,1')->name('schedule');
            Route::post('suppression/annuler', [TenantLifecycleController::class, 'cancel'])->middleware('throttle:10,1')->name('cancel');
            Route::post('protection', [TenantLifecycleController::class, 'protect'])->middleware('throttle:20,1')->name('protect');
            Route::post('export', [TenantLifecycleController::class, 'export'])->middleware('throttle:5,1')->name('export');
            Route::get('export/telecharger', [TenantLifecycleController::class, 'download'])->middleware('throttle:20,1')->name('download');
        });

        // Santé de l'application (super administrateur)
        Route::middleware('console.can:health.view')->prefix('sante')->name('health.')->group(function () {
            Route::get('/', [HealthController::class, 'index'])->name('index');
            Route::post('noter', [HealthController::class, 'note'])->name('note');
            Route::post('tester-alerte', [HealthController::class, 'testError'])->middleware('throttle:5,1')->name('test-error');
            Route::post('tester-email', [HealthController::class, 'testEmail'])->middleware('throttle:5,1')->name('test-email');
            Route::post('migrer', [HealthController::class, 'migrateAll'])->middleware('throttle:3,1')->name('migrate-all');
            Route::post('migrer/{tenant}', [HealthController::class, 'migrateTenant'])->middleware('throttle:20,1')->name('migrate');
            Route::post('sauvegarder', [HealthController::class, 'backupNow'])->middleware('throttle:3,1')->name('backup');
            Route::get('telecharger', [HealthController::class, 'download'])->middleware('throttle:20,1')->name('download');
        });

        // Textes légaux (super administrateur)
        Route::middleware('console.can:legal.manage')->prefix('textes-legaux')->name('legal.')->group(function () {
            Route::get('/', [LegalController::class, 'index'])->name('index');
            Route::put('{key}', [LegalController::class, 'save'])->middleware('throttle:30,1')->name('save');
            Route::post('{key}/publier', [LegalController::class, 'publish'])->middleware('throttle:10,1')->name('publish');
            Route::post('{key}/reinitialiser', [LegalController::class, 'reset'])->name('reset');
        });

        // Réglages (super administrateur)
        Route::middleware('console.can:settings.manage')->prefix('parametres')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::put('/', [SettingsController::class, 'update'])->middleware('throttle:20,1')->name('update');
            Route::post('maintenance', [SettingsController::class, 'maintenance'])->middleware('throttle:10,1')->name('maintenance');
        });

        // Journal d'activité et équipe (super administrateur)
        Route::middleware('console.can:audit.view')->group(function () {
            Route::get('journal', [AuditController::class, 'index'])->name('audit.index');
            Route::get('journal/export', [AuditController::class, 'export'])->middleware('throttle:10,1')->name('audit.export');
        });
        Route::middleware('console.can:team.manage')->prefix('equipe')->name('team.')->group(function () {
            Route::get('/', [TeamController::class, 'index'])->name('index');
            Route::post('/', [TeamController::class, 'store'])->middleware('throttle:20,1')->name('store');
            Route::put('{admin}', [TeamController::class, 'update'])->name('update');
            Route::post('{admin}/mot-de-passe', [TeamController::class, 'resetPassword'])->middleware('throttle:10,1')->name('reset');
        });
    });
});

// Toute autre adresse non reconnue (hors /api et /console) renvoie l'interface React : voir SpaController.
Route::fallback(SpaController::class);
