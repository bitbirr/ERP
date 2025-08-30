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
});
