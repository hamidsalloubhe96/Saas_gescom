<?php

use App\Http\Controllers\Central\ImpersonationController;
use App\Http\Controllers\Central\RegisterTenantController;
use App\Http\Controllers\Public\PublicConfigController;
use App\Http\Controllers\Public\PublicLegalController;
use App\Http\Controllers\Public\PublicReviewsController;
use App\Http\Controllers\Public\SignupRequestController;
use Illuminate\Support\Facades\Route;

// Routes centrales (hors tenant) : inscription d'une nouvelle entreprise.
Route::post('/register-tenant', RegisterTenantController::class)->middleware('throttle:5,1');
Route::post('/register-tenant/code', [RegisterTenantController::class, 'sendCode'])->middleware('throttle:3,1');

// Site public : demande d'accès (formulaire protégé) et avis publiés.
Route::post('/signup-requests', [SignupRequestController::class, 'store'])->middleware('throttle:5,1');
Route::get('/public/config', PublicConfigController::class)->middleware('throttle:60,1');
Route::get('/public/companies', [\App\Http\Controllers\Public\PublicCompaniesController::class, 'search'])->middleware('throttle:30,1');
Route::get('/public/plans', [\App\Http\Controllers\Public\PublicCompaniesController::class, 'plans'])->middleware('throttle:60,1');
Route::get('/public/version', fn () => response()->json(['version' => substr((string) config('app.version'), 0, 12)]))->middleware('throttle:120,1');
Route::post('/webhooks/payments', \App\Http\Controllers\PaymentWebhookController::class)->middleware('throttle:60,1');
Route::get('/public/legal', [PublicLegalController::class, 'index'])->middleware('throttle:60,1');
Route::get('/public/legal/{key}', [PublicLegalController::class, 'show'])->middleware('throttle:60,1');
Route::get('/public/reviews', [PublicReviewsController::class, 'index'])->middleware('throttle:60,1');

// Connexion d'assistance : échange du code à usage unique donné par la console contre un jeton.
Route::post('/impersonation/exchange', [ImpersonationController::class, 'exchange'])->middleware('throttle:20,1');
