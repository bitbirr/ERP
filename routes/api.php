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
        ->middleware('cap:products.read');
    Route::post('/products', [\App\Http\Controllers\ProductController::class, 'store'])
        ->middleware('cap:products.manage');
    Route::get('/products/{product}', [\App\Http\Controllers\ProductController::class, 'show'])
        ->middleware('cap:products.read');
    Route::patch('/products/{product}', [\App\Http\Controllers\ProductController::class, 'update'])
        ->middleware('cap:products.update');
    Route::delete('/products/{product}', [\App\Http\Controllers\ProductController::class, 'destroy'])
        ->middleware('cap:products.update');

    // Inventory API Routes
    Route::get('/inventory', [\App\Http\Controllers\InventoryController::class, 'index'])
        ->middleware('cap:inventory.read');
    Route::get('/inventory/{branch}/{product}', [\App\Http\Controllers\InventoryController::class, 'show'])
        ->middleware('cap:inventory.read');
    Route::post('/inventory/opening', [\App\Http\Controllers\InventoryController::class, 'opening'])
        ->middleware('cap:inventory.adjust');
    Route::post('/inventory/receive', [\App\Http\Controllers\InventoryController::class, 'receive'])
        ->middleware('cap:inventory.receive');
    Route::post('/inventory/reserve', [\App\Http\Controllers\InventoryController::class, 'reserve'])
        ->middleware('cap:inventory.reserve');
    Route::post('/inventory/unreserve', [\App\Http\Controllers\InventoryController::class, 'unreserve'])
        ->middleware('cap:inventory.unreserve');
    Route::post('/inventory/issue', [\App\Http\Controllers\InventoryController::class, 'issue'])
        ->middleware('cap:inventory.issue');
    Route::post('/inventory/transfer', [\App\Http\Controllers\InventoryController::class, 'transfer'])
        ->middleware('cap:inventory.transfer');
    Route::post('/inventory/adjust', [\App\Http\Controllers\InventoryController::class, 'adjust'])
        ->middleware('cap:inventory.adjust');
    Route::post('/inventory/receive/bulk', [\App\Http\Controllers\InventoryController::class, 'bulkReceive'])
        ->middleware('cap:inventory.receive');
    Route::post('/inventory/reserve/bulk', [\App\Http\Controllers\InventoryController::class, 'bulkReserve'])
        ->middleware('cap:inventory.reserve');

    // Stock Reporting API Routes
    Route::get('/reports/stock/onhand', [\App\Http\Controllers\InventoryController::class, 'stockOnHand'])
        ->middleware('cap:inventory.read');
    Route::get('/reports/stock/movements', [\App\Http\Controllers\InventoryController::class, 'stockMovements'])
        ->middleware('cap:inventory.read');
    Route::get('/reports/stock/valuation', [\App\Http\Controllers\InventoryController::class, 'stockValuation'])
        ->middleware('cap:inventory.read');
    Route::get('/reports/stock/reserved-backlog', [\App\Http\Controllers\InventoryController::class, 'reservedBacklog'])
        ->middleware('cap:inventory.read');
    Route::get('/audit/stock-movements', [\App\Http\Controllers\InventoryController::class, 'auditStockMovements'])
        ->middleware('cap:inventory.read');

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
        ->middleware('cap:inventory.read');

    // Reports API Routes
    Route::get('/reports/summary', [\App\Http\Controllers\ReportController::class, 'summary'])
        ->middleware('cap:reports.view');
    Route::get('/reports/inventory', [\App\Http\Controllers\ReportController::class, 'inventory'])
        ->middleware('cap:reports.view');
    Route::get('/reports/products', [\App\Http\Controllers\ReportController::class, 'products'])
        ->middleware('cap:reports.view');

    // Audit API Routes
    Route::get('/audit/logs', [\App\Http\Controllers\AuditController::class, 'logs'])
        ->middleware('cap:audit.view');
    Route::get('/audit/logs/{log}', [\App\Http\Controllers\AuditController::class, 'show'])
        ->middleware('cap:audit.view');

    // Voucher API Routes
    Route::post('/vouchers/batches', [\App\Http\Controllers\VoucherController::class, 'receiveBatch'])
        ->middleware('cap:vouchers.manage');
    Route::get('/vouchers/batches', [\App\Http\Controllers\VoucherController::class, 'listBatches'])
        ->middleware('cap:vouchers.view');
    Route::get('/vouchers/batches/{batchNumber}', [\App\Http\Controllers\VoucherController::class, 'getBatch'])
        ->middleware('cap:vouchers.view');
    Route::get('/vouchers/batches/{batchNumber}/available', [\App\Http\Controllers\VoucherController::class, 'getAvailableVouchers'])
        ->middleware('cap:vouchers.view');

    // Voucher Reservation Routes
    Route::post('/vouchers/reserve', [\App\Http\Controllers\VoucherController::class, 'reserveVouchers'])
        ->middleware('cap:vouchers.manage');
    Route::delete('/vouchers/reservations/{reservationId}', [\App\Http\Controllers\VoucherController::class, 'cancelReservation'])
        ->middleware('cap:vouchers.manage');
    Route::get('/vouchers/orders/{orderId}/reservations', [\App\Http\Controllers\VoucherController::class, 'getOrderReservations'])
        ->middleware('cap:vouchers.view');
    Route::patch('/vouchers/reservations/{reservationId}/extend', [\App\Http\Controllers\VoucherController::class, 'extendReservation'])
        ->middleware('cap:vouchers.manage');
    Route::post('/vouchers/reservations/cleanup', [\App\Http\Controllers\VoucherController::class, 'cleanupExpiredReservations'])
        ->middleware('cap:vouchers.manage');

    // Voucher Issuance Routes
    Route::post('/vouchers/issue', [\App\Http\Controllers\VoucherController::class, 'issueVouchers'])
        ->middleware('cap:vouchers.manage');
    Route::post('/vouchers/issue-by-reservations', [\App\Http\Controllers\VoucherController::class, 'issueVouchersByReservations'])
        ->middleware('cap:vouchers.manage');
    Route::get('/vouchers/orders/{orderId}/issuances', [\App\Http\Controllers\VoucherController::class, 'getOrderIssuances'])
        ->middleware('cap:vouchers.view');
    Route::get('/vouchers/fulfillments/{fulfillmentId}/issuances', [\App\Http\Controllers\VoucherController::class, 'getFulfillmentIssuances'])
        ->middleware('cap:vouchers.view');
    Route::patch('/vouchers/issuances/{issuanceId}/void', [\App\Http\Controllers\VoucherController::class, 'voidIssuance'])
        ->middleware('cap:vouchers.manage');

    // Customer API Routes
    Route::get('/customers', [\App\Http\Controllers\CustomerController::class, 'index']);
    Route::post('/customers', [\App\Http\Controllers\CustomerController::class, 'store']);
    Route::get('/customers/{customer}', [\App\Http\Controllers\CustomerController::class, 'show']);
    Route::patch('/customers/{customer}', [\App\Http\Controllers\CustomerController::class, 'update']);
    Route::delete('/customers/{customer}', [\App\Http\Controllers\CustomerController::class, 'destroy']);

    // Customer nested resources
    Route::get('/customers/{customer}/contacts', [\App\Http\Controllers\CustomerContactController::class, 'index']);
    Route::post('/customers/{customer}/contacts', [\App\Http\Controllers\CustomerContactController::class, 'store']);
    Route::get('/customers/{customer}/contacts/{contact}', [\App\Http\Controllers\CustomerContactController::class, 'show']);
    Route::patch('/customers/{customer}/contacts/{contact}', [\App\Http\Controllers\CustomerContactController::class, 'update']);
    Route::delete('/customers/{customer}/contacts/{contact}', [\App\Http\Controllers\CustomerContactController::class, 'destroy']);

    Route::get('/customers/{customer}/addresses', [\App\Http\Controllers\CustomerAddressController::class, 'index']);
    Route::post('/customers/{customer}/addresses', [\App\Http\Controllers\CustomerAddressController::class, 'store']);
    Route::get('/customers/{customer}/addresses/{address}', [\App\Http\Controllers\CustomerAddressController::class, 'show']);
    Route::patch('/customers/{customer}/addresses/{address}', [\App\Http\Controllers\CustomerAddressController::class, 'update']);
    Route::delete('/customers/{customer}/addresses/{address}', [\App\Http\Controllers\CustomerAddressController::class, 'destroy']);

    // Customer segments
    Route::get('/segments', [\App\Http\Controllers\CustomerSegmentController::class, 'index']);
    Route::post('/segments', [\App\Http\Controllers\CustomerSegmentController::class, 'store']);
    Route::get('/segments/{segment}', [\App\Http\Controllers\CustomerSegmentController::class, 'show']);
    Route::patch('/segments/{segment}', [\App\Http\Controllers\CustomerSegmentController::class, 'update']);
    Route::delete('/segments/{segment}', [\App\Http\Controllers\CustomerSegmentController::class, 'destroy']);
    Route::get('/segments/{segment}/members', [\App\Http\Controllers\CustomerSegmentController::class, 'members']);
    Route::post('/segments/preview', [\App\Http\Controllers\CustomerSegmentController::class, 'preview']);
});
