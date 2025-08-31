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

// Protected route example (requires authentication via Sanctum)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/api/transactions', [\App\Http\Controllers\TransactionController::class, 'index'])
    ->middleware(['cap:tx.view', 'throttle:10,1']);
Route::middleware(['auth:sanctum', 'cap:users.manage'])->group(function () {
    Route::post('/api/rbac/roles', [\App\Http\Controllers\RbacController::class, 'createRole']);
    Route::patch('/api/rbac/roles/{id}', [\App\Http\Controllers\RbacController::class, 'updateRole']);
    Route::post('/api/rbac/roles/{id}/capabilities', [\App\Http\Controllers\RbacController::class, 'syncRoleCapabilities']);
    Route::post('/api/rbac/users/{user}/roles', [\App\Http\Controllers\RbacController::class, 'assignUserRole']);
    Route::post('/api/rbac/rebuild', [\App\Http\Controllers\RbacController::class, 'rebuildRbacCache']);
});

// GL (General Ledger) API Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Journal routes
    Route::get('/api/gl/journals', [\App\Http\Controllers\GL\GlJournalController::class, 'index'])
        ->middleware('cap:gl.view');
    Route::post('/api/gl/journals', [\App\Http\Controllers\GL\GlJournalController::class, 'store'])
        ->middleware('cap:gl.create');
    Route::get('/api/gl/journals/{journal}', [\App\Http\Controllers\GL\GlJournalController::class, 'show'])
        ->middleware('cap:gl.view');
    Route::post('/api/gl/journals/{journal}/post', [\App\Http\Controllers\GL\GlJournalController::class, 'post'])
        ->middleware('cap:gl.post');
    Route::post('/api/gl/journals/{journal}/reverse', [\App\Http\Controllers\GL\GlJournalController::class, 'reverse'])
        ->middleware('cap:gl.reverse');
    Route::post('/api/gl/journals/{journal}/void', [\App\Http\Controllers\GL\GlJournalController::class, 'void'])
        ->middleware('cap:gl.reverse');
    Route::post('/api/gl/journals/{journal}/validate', [\App\Http\Controllers\GL\GlJournalController::class, 'validateDraft'])
        ->middleware('cap:gl.view');

    // Account routes
    Route::get('/api/gl/accounts', [\App\Http\Controllers\GL\GlAccountController::class, 'index'])
        ->middleware('cap:gl.view');
    Route::get('/api/gl/accounts/tree', [\App\Http\Controllers\GL\GlAccountController::class, 'tree'])
        ->middleware('cap:gl.view');
    Route::get('/api/gl/accounts/{account}', [\App\Http\Controllers\GL\GlAccountController::class, 'show'])
        ->middleware('cap:gl.view');
    Route::get('/api/gl/accounts/{account}/balance', [\App\Http\Controllers\GL\GlAccountController::class, 'balance'])
        ->middleware('cap:gl.view');

    // POS Receipt routes
    Route::post('/api/receipts', [\App\Http\Controllers\PosController::class, 'createReceipt'])
        ->middleware('cap:receipts.create');
    Route::patch('/api/receipts/{receipt}/void', [\App\Http\Controllers\PosController::class, 'voidReceipt'])
        ->middleware('cap:receipts.void');

    // Telebirr API Routes
    // Agent routes
    Route::get('/api/telebirr/agents', [\App\Http\Controllers\TelebirrController::class, 'agents'])
        ->middleware('cap:telebirr.view');
    Route::get('/api/telebirr/agents/{agent}', [\App\Http\Controllers\TelebirrController::class, 'agent'])
        ->middleware('cap:telebirr.view');
    Route::post('/api/telebirr/agents', [\App\Http\Controllers\TelebirrController::class, 'createAgent'])
        ->middleware('cap:telebirr.manage');
    Route::patch('/api/telebirr/agents/{agent}', [\App\Http\Controllers\TelebirrController::class, 'updateAgent'])
        ->middleware('cap:telebirr.manage');

    // Transaction routes
    Route::get('/api/telebirr/transactions', [\App\Http\Controllers\TelebirrController::class, 'transactions'])
        ->middleware('cap:telebirr.view');
    Route::get('/api/telebirr/transactions/{transaction}', [\App\Http\Controllers\TelebirrController::class, 'transaction'])
        ->middleware('cap:telebirr.view');
    Route::post('/api/telebirr/transactions/topup', [\App\Http\Controllers\TelebirrController::class, 'postTopup'])
        ->middleware('cap:telebirr.post');
    Route::post('/api/telebirr/transactions/issue', [\App\Http\Controllers\TelebirrController::class, 'postIssue'])
        ->middleware('cap:telebirr.post');
    Route::post('/api/telebirr/transactions/repay', [\App\Http\Controllers\TelebirrController::class, 'postRepay'])
        ->middleware('cap:telebirr.post');
    Route::post('/api/telebirr/transactions/loan', [\App\Http\Controllers\TelebirrController::class, 'postLoan'])
        ->middleware('cap:telebirr.post');
    Route::patch('/api/telebirr/transactions/{transaction}/void', [\App\Http\Controllers\TelebirrController::class, 'voidTransaction'])
        ->middleware('cap:telebirr.void');

    // Reconciliation routes
    Route::get('/api/telebirr/reconciliation', [\App\Http\Controllers\TelebirrController::class, 'reconciliation'])
        ->middleware('cap:telebirr.view');

    // Reporting routes
    Route::get('/api/telebirr/reports/agent-balances', [\App\Http\Controllers\TelebirrController::class, 'agentBalances'])
        ->middleware('cap:telebirr.view');
    Route::get('/api/telebirr/reports/transaction-summary', [\App\Http\Controllers\TelebirrController::class, 'transactionSummary'])
        ->middleware('cap:telebirr.view');
});
