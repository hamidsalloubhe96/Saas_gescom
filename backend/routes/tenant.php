<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SalesDocumentController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

/*
| Routes tenant : le frontend envoie l'identifiant de l'entreprise dans l'en-tête
| "X-Tenant" (pas besoin de sous-domaine wildcard sur Render).
|
| Chaque route est protégée par une permission (voir config/permissions.php) :
|   permission:xxx.view   -> lecture
|   permission:xxx.manage -> création / modification / suppression
*/
Route::prefix('api')
    ->middleware(['api', 'maintenance', InitializeTenancyByRequestData::class, 'tenant.active'])
    ->where(['id' => '[0-9]+', 'paymentId' => '[0-9]+'])
    ->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

        // Mot de passe oublié : code à 6 chiffres par e-mail (limites strictes : le serveur dit si l'e-mail existe).
        Route::post('/password/forgot', [\App\Http\Controllers\Auth\PasswordResetController::class, 'forgot'])->middleware(['throttle:5,1,forgot_m', 'throttle:20,60,forgot_h']);
        Route::post('/password/reset', [\App\Http\Controllers\Auth\PasswordResetController::class, 'reset'])->middleware('throttle:10,1');

        Route::middleware(['auth:sanctum', 'impersonation.readonly'])->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/password', [AuthController::class, 'changePassword'])->middleware('throttle:10,1');
            Route::post('/logout', [AuthController::class, 'logout']);

            Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view');
            Route::post('reviews', [ReviewController::class, 'store'])->middleware('throttle:5,60');

            // --- Support (ouvert à tout utilisateur connecté, même abonnement expiré) ---
            Route::get('support', [\App\Http\Controllers\SupportController::class, 'index']);
            Route::post('support', [\App\Http\Controllers\SupportController::class, 'store'])->middleware('throttle:10,60');
            Route::get('support/{id}', [\App\Http\Controllers\SupportController::class, 'show'])->whereNumber('id');
            Route::post('support/{id}/replies', [\App\Http\Controllers\SupportController::class, 'reply'])->whereNumber('id')->middleware('throttle:30,60');

            // --- Abonnement (reste ouvert même si l'abonnement est terminé : voir EnsureTenantIsActive) ---
            Route::middleware('permission:billing.manage')->group(function () {
                Route::get('subscription', [SubscriptionController::class, 'show']);
                Route::post('subscription/payments', [SubscriptionController::class, 'declare'])->middleware('throttle:10,1');
                Route::get('subscription/payments/{paymentId}/receipt', [SubscriptionController::class, 'receipt']);
            });

            // --- Points de vente (siège) et transferts de stock ---
            Route::middleware(['permission:branches.manage', 'hq'])->group(function () {
                Route::get('branches', [\App\Http\Controllers\BranchController::class, 'index']);
                Route::get('branches/overview', [\App\Http\Controllers\BranchController::class, 'overview']);
                Route::post('branches', [\App\Http\Controllers\BranchController::class, 'store']);
                Route::put('branches/{id}', [\App\Http\Controllers\BranchController::class, 'update']);
                Route::get('products/{id}/branches', [\App\Http\Controllers\BranchController::class, 'productBranches']);
                Route::put('products/{id}/branch-prices', [\App\Http\Controllers\BranchController::class, 'setPrice']);
            });
            Route::middleware('permission:transfers.manage,transfers.receive')->group(function () {
                Route::get('transfers', [\App\Http\Controllers\StockTransferController::class, 'index']);
                Route::get('transfers/{id}', [\App\Http\Controllers\StockTransferController::class, 'show']);
                Route::post('transfers', [\App\Http\Controllers\StockTransferController::class, 'store']);
                Route::post('transfers/{id}/receive', [\App\Http\Controllers\StockTransferController::class, 'receive']);
                Route::post('transfers/{id}/cancel', [\App\Http\Controllers\StockTransferController::class, 'cancel']);
            });
            Route::post('transfers/{id}/ship', [\App\Http\Controllers\StockTransferController::class, 'ship'])->middleware(['permission:transfers.manage', 'hq']);

            // --- Utilisateurs ---
            Route::middleware(['permission:users.manage', 'hq'])->group(function () {
                Route::get('roles', [UserController::class, 'roles']);
                Route::get('users', [UserController::class, 'index']);
                Route::post('users', [UserController::class, 'store']);
                Route::put('users/{id}', [UserController::class, 'update']);
            });

            // --- Clients, fournisseurs, produits : lecture / gestion ---
            foreach ([
                'customers' => CustomerController::class,
                'suppliers' => SupplierController::class,
                'products' => ProductController::class,
            ] as $uri => $controller) {
                Route::middleware("permission:$uri.view")->group(function () use ($uri, $controller) {
                    Route::get($uri, [$controller, 'index']);
                    Route::get("$uri/{id}", [$controller, 'show']);
                });
                // Le catalogue et les fournisseurs sont gérés par le siège ; les clients par tous.
                Route::middleware($uri === 'customers' ? ["permission:$uri.manage"] : ["permission:$uri.manage", 'hq'])->group(function () use ($uri, $controller) {
                    Route::post($uri, [$controller, 'store']);
                    Route::put("$uri/{id}", [$controller, 'update']);
                    Route::delete("$uri/{id}", [$controller, 'destroy']);
                });
            }

            // --- Catégories et stock ---
            Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:products.view');
            Route::middleware(['permission:products.manage', 'hq'])->group(function () {
                Route::post('categories', [CategoryController::class, 'store']);
                Route::put('categories/{id}', [CategoryController::class, 'update']);
                Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
            });
            Route::get('products/{id}/movements', [ProductController::class, 'movements'])->middleware('permission:products.view');
            Route::post('products/{id}/movements', [ProductController::class, 'addMovement'])->middleware('permission:stock.move');

            // --- Devis et factures ---
            Route::middleware('permission:sales.view')->group(function () {
                Route::get('documents', [SalesDocumentController::class, 'index']);
                Route::get('documents/{id}', [SalesDocumentController::class, 'show']);
            });
            Route::middleware('permission:sales.manage')->group(function () {
                Route::post('documents', [SalesDocumentController::class, 'store']);
                Route::put('documents/{id}', [SalesDocumentController::class, 'update']);
                Route::delete('documents/{id}', [SalesDocumentController::class, 'destroy']);
                Route::post('documents/{id}/transition', [SalesDocumentController::class, 'transition']);
                Route::post('documents/{id}/convert', [SalesDocumentController::class, 'convert']);
            });
            Route::middleware('permission:sales.pay')->group(function () {
                Route::post('documents/{id}/payments', [SalesDocumentController::class, 'addPayment']);
                Route::delete('documents/{id}/payments/{paymentId}', [SalesDocumentController::class, 'removePayment']);
            });

            // --- Caisse ---
            Route::prefix('pos')->group(function () {
                Route::middleware('permission:pos.use')->group(function () {
                    Route::get('session', [PosController::class, 'session']);
                    Route::post('session/open', [PosController::class, 'open']);
                    Route::post('session/close', [PosController::class, 'close']);
                    Route::get('sessions', [PosController::class, 'sessions']);
                    Route::get('sales', [PosController::class, 'sales']);
                    Route::get('sales/{id}', [PosController::class, 'showSale']);
                    Route::post('sales', [PosController::class, 'sale']);
                });
                Route::post('sales/{id}/void', [PosController::class, 'void'])->middleware('permission:pos.void');
            });

            // --- Achats ---
            Route::middleware(['permission:purchases.view', 'hq'])->group(function () {
                Route::get('purchases', [PurchaseOrderController::class, 'index']);
                Route::get('purchases/{id}', [PurchaseOrderController::class, 'show']);
            });
            Route::middleware(['permission:purchases.manage', 'hq'])->group(function () {
                Route::post('purchases', [PurchaseOrderController::class, 'store']);
                Route::put('purchases/{id}', [PurchaseOrderController::class, 'update']);
                Route::delete('purchases/{id}', [PurchaseOrderController::class, 'destroy']);
                Route::post('purchases/{id}/transition', [PurchaseOrderController::class, 'transition']);
            });
            Route::post('purchases/{id}/receive', [PurchaseOrderController::class, 'receive'])->middleware(['permission:purchases.receive', 'hq']);
            Route::middleware(['permission:purchases.pay', 'hq'])->group(function () {
                Route::post('purchases/{id}/payments', [PurchaseOrderController::class, 'addPayment']);
                Route::delete('purchases/{id}/payments/{paymentId}', [PurchaseOrderController::class, 'removePayment']);
            });
        });
    });
