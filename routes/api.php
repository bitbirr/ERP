<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public route example
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

// Sanctum token creation route
Route::post('/sanctum/token', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'device_name' => 'required',
    ]);

    $user = \App\Models\User::where('email', $request->email)->first();

    if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    return response()->json([
        'token' => $user->createToken($request->device_name)->plainTextToken,
        'user' => $user->only(['id', 'name', 'email'])
    ]);
});

// Protected route example (requires authentication via Sanctum)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json(['user' => $request->user()]);
});

Route::middleware(['auth:sanctum', 'cap:users.manage'])->group(function () {
    Route::post('/rbac/roles', [\App\Http\Controllers\RbacController::class, 'createRole']);
    Route::patch('/rbac/roles/{id}', [\App\Http\Controllers\RbacController::class, 'updateRole']);
    Route::post('/rbac/roles/{id}/capabilities', [\App\Http\Controllers\RbacController::class, 'syncRoleCapabilities']);
    Route::post('/rbac/users/{user}/roles', [\App\Http\Controllers\RbacController::class, 'assignUserRole']);
    Route::post('/rbac/rebuild', [\App\Http\Controllers\RbacController::class, 'rebuildRbacCache']);
});

// GL (General Ledger) API Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Journal routes
    Route::get('/gl/journals', [\App\Http\Controllers\GL\GlJournalController::class, 'index'])
        ->middleware(['cap:gl.view']);
    Route::post('/gl/journals', [\App\Http\Controllers\GL\GlJournalController::class, 'store'])
        ->middleware(['cap:gl.create']);
    Route::get('/gl/journals/{journal}', [\App\Http\Controllers\GL\GlJournalController::class, 'show'])
        ->middleware(['cap:gl.view']);
    Route::post('/gl/journals/{journal}/post', [\App\Http\Controllers\GL\GlJournalController::class, 'post'])
        ->middleware(['cap:gl.post']);
    Route::post('/gl/journals/{journal}/reverse', [\App\Http\Controllers\GL\GlJournalController::class, 'reverse'])
        ->middleware(['cap:gl.reverse']);
    Route::post('/gl/journals/{journal}/void', [\App\Http\Controllers\GL\GlJournalController::class, 'void'])
        ->middleware(['cap:gl.reverse']);
    Route::post('/gl/journals/{journal}/validate', [\App\Http\Controllers\GL\GlJournalController::class, 'validateDraft'])
        ->middleware(['cap:gl.view']);

    // Account routes
    Route::get('/gl/accounts', [\App\Http\Controllers\GL\GlAccountController::class, 'index'])
        ->middleware('cap:gl.view');
    Route::get('/gl/accounts/tree', [\App\Http\Controllers\GL\GlAccountController::class, 'tree'])
        ->middleware('cap:gl.view');
    Route::get('/gl/accounts/{account}', [\App\Http\Controllers\GL\GlAccountController::class, 'show'])
        ->middleware('cap:gl.view');
    Route::get('/gl/accounts/{account}/balance', [\App\Http\Controllers\GL\GlAccountController::class, 'balance'])
        ->middleware('cap:gl.view');

    // POS Receipt routes
    Route::post('/receipts', [\App\Http\Controllers\PosController::class, 'createReceipt'])
        ->middleware('cap:receipts.create');
    Route::patch('/receipts/{receipt}/void', [\App\Http\Controllers\PosController::class, 'voidReceipt'])
        ->middleware('cap:receipts.void');

    // Telebirr API Routes
    // Agent routes
    Route::get('/telebirr/agents', [\App\Http\Controllers\TelebirrController::class, 'agents'])
        ->middleware('cap:telebirr.view');
    Route::get('/telebirr/agents/{agent}', [\App\Http\Controllers\TelebirrController::class, 'agent'])
        ->middleware('cap:telebirr.view');
    Route::post('/telebirr/agents', [\App\Http\Controllers\TelebirrController::class, 'createAgent'])
        ->middleware('cap:telebirr.manage');
    Route::patch('/telebirr/agents/{agent}', [\App\Http\Controllers\TelebirrController::class, 'updateAgent'])
        ->middleware('cap:telebirr.manage');

    // Transaction routes
    Route::get('/telebirr/transactions', [\App\Http\Controllers\TelebirrController::class, 'transactions'])
        ->middleware('cap:telebirr.view');
    Route::get('/telebirr/transactions/{transaction}', [\App\Http\Controllers\TelebirrController::class, 'transaction'])
        ->middleware('cap:telebirr.view');
    Route::post('/telebirr/transactions/topup', [\App\Http\Controllers\TelebirrController::class, 'postTopup'])
        ->middleware('cap:telebirr.post');
    Route::post('/telebirr/transactions/issue', [\App\Http\Controllers\TelebirrController::class, 'postIssue'])
        ->middleware('cap:telebirr.post');
    Route::post('/telebirr/transactions/repay', [\App\Http\Controllers\TelebirrController::class, 'postRepay'])
        ->middleware('cap:telebirr.post');
    Route::post('/telebirr/transactions/loan', [\App\Http\Controllers\TelebirrController::class, 'postLoan'])
        ->middleware('cap:telebirr.post');
    Route::patch('/telebirr/transactions/{transaction}/void', [\App\Http\Controllers\TelebirrController::class, 'voidTransaction'])
        ->middleware('cap:telebirr.void');

    // Reconciliation routes
    Route::get('/telebirr/reconciliation', [\App\Http\Controllers\TelebirrController::class, 'reconciliation'])
        ->middleware('cap:telebirr.view');

    // Reporting routes
    Route::get('/telebirr/reports/agent-balances', [\App\Http\Controllers\TelebirrController::class, 'agentBalances'])
        ->middleware('cap:telebirr.view');
    Route::get('/telebirr/reports/transaction-summary', [\App\Http\Controllers\TelebirrController::class, 'transactionSummary'])
        ->middleware('cap:telebirr.view');

    // Products API Routes
    Route::get('/products', [\App\Http\Controllers\ProductController::class, 'index'])
        ->middleware('cap:products.view');
    Route::post('/products', [\App\Http\Controllers\ProductController::class, 'store'])
        ->middleware('cap:products.manage');
    Route::get('/products/{product}', [\App\Http\Controllers\ProductController::class, 'show'])
        ->middleware('cap:products.view');
    Route::patch('/products/{product}', [\App\Http\Controllers\ProductController::class, 'update'])
        ->middleware('cap:products.manage');
    Route::delete('/products/{product}', [\App\Http\Controllers\ProductController::class, 'destroy'])
        ->middleware('cap:products.manage');

    // Inventory API Routes
    Route::get('/inventory', [\App\Http\Controllers\InventoryController::class, 'index'])
        ->middleware('cap:inventory.view');
    Route::get('/inventory/{product}/{branch}', [\App\Http\Controllers\InventoryController::class, 'show'])
        ->middleware('cap:inventory.view');
    Route::post('/inventory/opening', [\App\Http\Controllers\InventoryController::class, 'opening'])
        ->middleware('cap:inventory.manage');
    Route::post('/inventory/receive', [\App\Http\Controllers\InventoryController::class, 'receive'])
        ->middleware('cap:inventory.manage');
    Route::post('/inventory/reserve', [\App\Http\Controllers\InventoryController::class, 'reserve'])
        ->middleware('cap:inventory.manage');
    Route::post('/inventory/unreserve', [\App\Http\Controllers\InventoryController::class, 'unreserve'])
        ->middleware('cap:inventory.manage');
    Route::post('/inventory/issue', [\App\Http\Controllers\InventoryController::class, 'issue'])
        ->middleware('cap:inventory.manage');
    Route::post('/inventory/transfer', [\App\Http\Controllers\InventoryController::class, 'transfer'])
        ->middleware('cap:inventory.manage');
    Route::post('/inventory/adjust', [\App\Http\Controllers\InventoryController::class, 'adjust'])
        ->middleware('cap:inventory.manage');

    // Stock Movement API Routes
    Route::get('/stock-movements', [\App\Http\Controllers\StockMovementController::class, 'index'])
        ->middleware('cap:inventory.view');
    Route::get('/stock-movements/{stockMovement}', [\App\Http\Controllers\StockMovementController::class, 'show'])
        ->middleware('cap:inventory.view');
    Route::get('/stock-movements/reports/summary', [\App\Http\Controllers\StockMovementController::class, 'summary'])
        ->middleware('cap:inventory.view');
    Route::get('/stock-movements/reports/by-product/{product}', [\App\Http\Controllers\StockMovementController::class, 'byProduct'])
        ->middleware('cap:inventory.view');
    Route::get('/stock-movements/reports/by-branch/{branch}', [\App\Http\Controllers\StockMovementController::class, 'byBranch'])
        ->middleware('cap:inventory.view');
});
