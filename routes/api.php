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
    \Illuminate\Support\Facades\Log::info('Sanctum token request received', [
        'email' => $request->email,
        'device_name' => $request->device_name,
        'headers' => $request->headers->all(),
        'method' => $request->method(),
        'url' => $request->fullUrl()
    ]);

    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'device_name' => 'required',
    ]);

    $user = \App\Models\User::where('email', $request->email)->first();

    if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
        \Illuminate\Support\Facades\Log::warning('Sanctum token authentication failed', [
            'email' => $request->email,
            'user_found' => $user ? true : false
        ]);
        throw \Illuminate\Validation\ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    \Illuminate\Support\Facades\Log::info('Sanctum token created successfully', [
        'user_id' => $user->id,
        'email' => $user->email
    ]);

    return response()->json([
        'token' => $user->createToken($request->device_name)->plainTextToken,
        'user' => $user->only(['id', 'name', 'email'])
    ]);
});

// Sanctum token refresh route
Route::middleware('auth:sanctum')->post('/sanctum/refresh', function (Request $request) {
    \Illuminate\Support\Facades\Log::info('Sanctum token refresh request received', [
        'user_id' => $request->user()->id,
        'email' => $request->user()->email,
        'device_name' => $request->device_name ?? 'web-app',
    ]);

    $request->validate([
        'device_name' => 'sometimes|required|string|max:255',
    ]);

    $deviceName = $request->device_name ?? 'web-app';

    // Delete current token
    $request->user()->currentAccessToken()->delete();

    \Illuminate\Support\Facades\Log::info('Sanctum token refreshed successfully', [
        'user_id' => $request->user()->id,
        'email' => $request->user()->email
    ]);

    return response()->json([
        'token' => $request->user()->createToken($deviceName)->plainTextToken,
        'user' => $request->user()->only(['id', 'name', 'email'])
    ]);
});

// Protected route example (requires authentication via Sanctum)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json(['user' => $request->user()]);
});

Route::middleware(['auth:sanctum', 'cap:users.manage'])->group(function () {
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index']);
    Route::post('/users', [\App\Http\Controllers\UserController::class, 'store']);
    Route::get('/users/{user}', [\App\Http\Controllers\UserController::class, 'show']);
    Route::patch('/users/{user}', [\App\Http\Controllers\UserController::class, 'update']);
    Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy']);
    Route::get('/rbac/roles', [\App\Http\Controllers\RbacController::class, 'getRoles']);
    Route::post('/rbac/roles', [\App\Http\Controllers\RbacController::class, 'createRole']);
    Route::patch('/rbac/roles/{id}', [\App\Http\Controllers\RbacController::class, 'updateRole']);
    Route::post('/rbac/roles/{id}/capabilities', [\App\Http\Controllers\RbacController::class, 'syncRoleCapabilities']);
    Route::post('/rbac/users/{user}/roles', [\App\Http\Controllers\RbacController::class, 'assignUserRole']);
    Route::get('/rbac/users/{user}/permissions', [\App\Http\Controllers\RbacController::class, 'getUserPermissions']);
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
    Route::post('/gl/accounts', [\App\Http\Controllers\GL\GlAccountController::class, 'store'])
        ->middleware('cap:gl.create');
    Route::get('/gl/accounts/tree', [\App\Http\Controllers\GL\GlAccountController::class, 'tree'])
        ->middleware('cap:gl.view');
    Route::get('/gl/accounts/summary', [\App\Http\Controllers\GL\GlAccountController::class, 'summary'])
        ->middleware('cap:gl.view');
    Route::get('/gl/accounts/{account}', [\App\Http\Controllers\GL\GlAccountController::class, 'show'])
        ->middleware('cap:gl.view');
    Route::patch('/gl/accounts/{account}', [\App\Http\Controllers\GL\GlAccountController::class, 'update'])
        ->middleware('cap:gl.update');
    Route::delete('/gl/accounts/{account}', [\App\Http\Controllers\GL\GlAccountController::class, 'destroy'])
        ->middleware('cap:gl.delete');
    Route::get('/gl/accounts/{account}/balance', [\App\Http\Controllers\GL\GlAccountController::class, 'balance'])
        ->middleware('cap:gl.view');

    // POS Receipt routes
    Route::get('/receipts', [\App\Http\Controllers\PosController::class, 'index'])
        ->middleware('cap:receipts.view');
    Route::post('/receipts', [\App\Http\Controllers\PosController::class, 'createReceipt'])
        ->middleware('cap:receipts.create');
    Route::get('/receipts/{receipt}', [\App\Http\Controllers\PosController::class, 'show'])
        ->middleware('cap:receipts.view');
    Route::patch('/receipts/{receipt}/void', [\App\Http\Controllers\PosController::class, 'voidReceipt'])
        ->middleware('cap:receipts.void');

    // Order routes
    Route::get('/orders', [\App\Http\Controllers\OrderController::class, 'index'])
        ->middleware('cap:orders.view');
    Route::post('/orders', [\App\Http\Controllers\OrderController::class, 'store'])
        ->middleware('cap:orders.create');
    Route::get('/orders/{order}', [\App\Http\Controllers\OrderController::class, 'show'])
        ->middleware('cap:orders.view');
    Route::patch('/orders/{order}', [\App\Http\Controllers\OrderController::class, 'update'])
        ->middleware('cap:orders.update');
    Route::patch('/orders/{order}/approve', [\App\Http\Controllers\OrderController::class, 'approve'])
        ->middleware('cap:orders.approve');
    Route::patch('/orders/{order}/cancel', [\App\Http\Controllers\OrderController::class, 'cancel'])
        ->middleware('cap:orders.cancel');
    Route::delete('/orders/{order}', [\App\Http\Controllers\OrderController::class, 'destroy'])
        ->middleware('cap:orders.delete');

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
    Route::get('/telebirr/dashboard', [\App\Http\Controllers\TelebirrController::class, 'dashboard'])
        ->middleware('cap:telebirr.view');

    // Product Categories API Routes
    Route::get('/product-categories', [\App\Http\Controllers\ProductCategoryController::class, 'index'])
        ->middleware('cap:products.read');
    Route::post('/product-categories', [\App\Http\Controllers\ProductCategoryController::class, 'store'])
        ->middleware('cap:products.manage');
    Route::get('/product-categories/{category}', [\App\Http\Controllers\ProductCategoryController::class, 'show'])
        ->middleware('cap:products.read');
    Route::patch('/product-categories/{category}', [\App\Http\Controllers\ProductCategoryController::class, 'update'])
        ->middleware('cap:products.update');
    Route::delete('/product-categories/{category}', [\App\Http\Controllers\ProductCategoryController::class, 'destroy'])
        ->middleware('cap:products.update');

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
        ->middleware('cap:inventory.read');
    Route::get('/stock-movements/{stockMovement}', [\App\Http\Controllers\StockMovementController::class, 'show'])
        ->middleware('cap:inventory.read');
    Route::get('/stock-movements/reports/summary', [\App\Http\Controllers\StockMovementController::class, 'summary'])
        ->middleware('cap:inventory.read');
    Route::get('/stock-movements/reports/by-product/{product}', [\App\Http\Controllers\StockMovementController::class, 'byProduct'])
        ->middleware('cap:inventory.read');
    Route::get('/stock-movements/reports/by-branch/{branch}', [\App\Http\Controllers\StockMovementController::class, 'byBranch'])
        ->middleware('cap:inventory.read');

    // Reports API Routes
    Route::get('/reports/summary', [\App\Http\Controllers\ReportController::class, 'summary'])
        ->middleware('cap:reports.view');
    Route::get('/reports/dashboard', [\App\Http\Controllers\ReportController::class, 'dashboard'])
        ->middleware('cap:reports.view');
    Route::get('/reports/orders-summary', [\App\Http\Controllers\ReportController::class, 'ordersSummary'])
        ->middleware('cap:reports.view');
    Route::get('/reports/revenue-over-time', [\App\Http\Controllers\ReportController::class, 'revenueOverTime'])
        ->middleware('cap:reports.view');
    Route::get('/reports/top-selling-products', [\App\Http\Controllers\ReportController::class, 'topSellingProducts'])
        ->middleware('cap:reports.view');
    Route::get('/reports/low-stock-items', [\App\Http\Controllers\ReportController::class, 'lowStockItems'])
        ->middleware('cap:reports.view');
    Route::get('/reports/recent-orders', [\App\Http\Controllers\ReportController::class, 'recentOrders'])
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

    // Customer stats and duplicate check (must come before parameterized routes)
    Route::get('/customers/stats', [\App\Http\Controllers\CustomerController::class, 'stats']);
    Route::get('/customers/check-duplicate', [\App\Http\Controllers\CustomerController::class, 'checkDuplicate']);

    // Customer specific routes
    Route::get('/customers/{customer}', [\App\Http\Controllers\CustomerController::class, 'show']);
    Route::patch('/customers/{customer}', [\App\Http\Controllers\CustomerController::class, 'update']);
    Route::delete('/customers/{customer}', [\App\Http\Controllers\CustomerController::class, 'destroy']);

    // Customer orders and debt routes
    Route::get('/customers/{customer}/orders', [\App\Http\Controllers\CustomerController::class, 'orders']);
    Route::get('/customers/{customer}/pending-debt', [\App\Http\Controllers\CustomerController::class, 'pendingDebt']);

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

    // Services
    Route::get('/services', [\App\Http\Controllers\ServiceController::class, 'index']);
    Route::post('/services', [\App\Http\Controllers\ServiceController::class, 'store']);
    Route::get('/services/{service}', [\App\Http\Controllers\ServiceController::class, 'show']);
    Route::patch('/services/{service}', [\App\Http\Controllers\ServiceController::class, 'update']);
    Route::delete('/services/{service}', [\App\Http\Controllers\ServiceController::class, 'destroy']);

    // Category API Routes
    Route::get('/categories', [\App\Http\Controllers\CategoryController::class, 'index'])
        ->middleware('cap:category.view');
    Route::post('/categories', [\App\Http\Controllers\CategoryController::class, 'store'])
        ->middleware('cap:category.create');
    Route::get('/categories/{category}', [\App\Http\Controllers\CategoryController::class, 'show'])
        ->middleware('cap:category.view');
    Route::patch('/categories/{category}', [\App\Http\Controllers\CategoryController::class, 'update'])
        ->middleware('cap:category.update');
    Route::delete('/categories/{category}', [\App\Http\Controllers\CategoryController::class, 'destroy'])
        ->middleware('cap:category.delete');

    // Category management routes
    Route::post('/categories/assign-customer', [\App\Http\Controllers\CategoryController::class, 'assignCustomer'])
        ->middleware('cap:category.assign');
    Route::post('/categories/remove-customer', [\App\Http\Controllers\CategoryController::class, 'removeCustomer'])
        ->middleware('cap:category.assign');
    Route::get('/categories/stats', [\App\Http\Controllers\CategoryController::class, 'stats'])
        ->middleware('cap:category.view');

    // Loyalty API Routes
    Route::get('/loyalty/top-customers', [\App\Http\Controllers\LoyaltyController::class, 'getTopCustomers'])
        ->middleware('cap:loyalty.view');
    Route::post('/loyalty/discounts/generate', [\App\Http\Controllers\LoyaltyController::class, 'generateDiscount'])
        ->middleware('cap:loyalty.manage');
    Route::get('/loyalty/customers/{customer}/points', [\App\Http\Controllers\LoyaltyController::class, 'getCustomerPoints'])
        ->middleware('cap:loyalty.view');
    Route::get('/loyalty/customers/{customer}/discounts', [\App\Http\Controllers\LoyaltyController::class, 'getCustomerDiscounts'])
        ->middleware('cap:loyalty.view');

    // Branch API Routes
    Route::get('/branches', [\App\Http\Controllers\BranchController::class, 'index'])
        ->middleware('cap:branches.view');
    Route::post('/branches', [\App\Http\Controllers\BranchController::class, 'store'])
        ->middleware('cap:branches.create');
    Route::get('/branches/{branch}', [\App\Http\Controllers\BranchController::class, 'show'])
        ->middleware('cap:branches.view');
    Route::patch('/branches/{branch}', [\App\Http\Controllers\BranchController::class, 'update'])
        ->middleware('cap:branches.update');
    Route::delete('/branches/{branch}', [\App\Http\Controllers\BranchController::class, 'destroy'])
        ->middleware('cap:branches.delete');
    Route::get('/branches/uuids', [\App\Http\Controllers\BranchController::class, 'fetchUuids'])
        ->middleware('cap:branches.view');

    // Bank Account API Routes
    Route::get('/accounts', [\App\Http\Controllers\BankAccountController::class, 'index'])
        ->middleware('cap:accounts.view');
    Route::post('/accounts', [\App\Http\Controllers\BankAccountController::class, 'store'])
        ->middleware('cap:accounts.create');
    Route::get('/accounts/{account}', [\App\Http\Controllers\BankAccountController::class, 'show'])
        ->middleware('cap:accounts.view');
    Route::patch('/accounts/{account}', [\App\Http\Controllers\BankAccountController::class, 'update'])
        ->middleware('cap:accounts.update');
    Route::delete('/accounts/{account}', [\App\Http\Controllers\BankAccountController::class, 'destroy'])
        ->middleware('cap:accounts.delete');
});
