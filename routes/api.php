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

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/api/receipts', [\App\Http\Controllers\PosController::class, 'createReceipt'])
        ->middleware('cap:receipts.create');
    Route::patch('/api/receipts/{receipt}/void', [\App\Http\Controllers\PosController::class, 'voidReceipt'])
        ->middleware('cap:receipts.void');
});
