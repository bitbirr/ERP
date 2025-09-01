<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Branch;
use App\Models\Receipt;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\GlJournal;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class PosApiIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $adminUser;
    protected $salesUser;
    protected $managerUser;
    protected $unauthorizedUser;
    protected $branch;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders to set up test data
        Artisan::call('db:seed', ['--class' => 'DatabaseSeeder']);

        // Rebuild RBAC cache
        Artisan::call('rbac:rebuild');

        // Assign receipts.create capability to the default test user
        \App\Models\UserPolicy::create([
            'user_id' => $this->user->id,
            'branch_id' => null,
            'capability_key' => 'receipts.create',
            'granted' => true,
        ]);

        // Assign receipts.void capability for void operations
        \App\Models\UserPolicy::create([
            'user_id' => $this->user->id,
            'branch_id' => null,
            'capability_key' => 'receipts.void',
            'granted' => true,
        ]);

        // Get test branch and product
        $this->branch = Branch::where('code', 'main')->first() ?? Branch::factory()->create(['code' => 'main', 'name' => 'Main Branch']);
        $this->product = Product::where('code', 'P001')->first() ?? Product::factory()->create(['code' => 'P001', 'name' => 'Yimulu']);

        // Set up initial inventory
        $inventoryService = app(\App\Application\Services\InventoryService::class);
        $inventoryService->openingBalance($this->product, $this->branch, 100);
    }

    /** @test */
    public function test_basic_route_access()
    {
        // Test the ping route first to see if routes are working
        $response = $this->getJson('/api/ping');
        $response->assertStatus(200)->assertJson(['message' => 'pong']);
    }

    /** @test */
    public function user_can_create_receipt_successfully()
    {
        $receiptData = $this->getValidReceiptData();

        $response = $this->actingAs($this->user)
            ->postJson('/api/receipts', $receiptData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'number',
                'branch_id',
                'status',
                'subtotal',
                'grand_total'
            ]);

        // Verify receipt was created
        $this->assertDatabaseHas('receipts', [
            'branch_id' => $this->branch->id,
            'status' => 'DRAFT'
        ]);

        // Verify inventory was reduced
        $inventoryItem = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first();
        $this->assertEquals(95, $inventoryItem->on_hand); // 100 - 5

        // Verify GL journal was created
        $this->assertDatabaseHas('gl_journals', [
            'description' => 'Receipt #' . $response->json('id')
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create_receipt',
            'subject_type' => Receipt::class
        ]);
    }

    /** @test */
    public function sales_user_can_create_receipt()
    {
        $receiptData = $this->getValidReceiptData();

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->postJson('/api/receipts', $receiptData);

        $response->assertStatus(201);
    }

    /** @test */
    public function unauthorized_user_cannot_create_receipt()
    {
        $receiptData = $this->getValidReceiptData();

        $response = $this->actingAs($this->unauthorizedUser, 'sanctum')
            ->postJson('/api/receipts', $receiptData);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'message' => 'Missing required capability: receipts.create'
            ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_receipt()
    {
        $receiptData = $this->getValidReceiptData();

        $response = $this->postJson('/api/receipts', $receiptData);

        $response->assertStatus(401);
    }

    /** @test */
    public function manager_can_void_receipt()
    {
        // First create a receipt
        $receipt = $this->createPostedReceipt();

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->patchJson("/api/receipts/{$receipt->id}/void", []);

        $response->assertStatus(200);

        // Verify receipt was voided
        $receipt->refresh();
        $this->assertEquals('voided', $receipt->status);
        $this->assertNotNull($receipt->voided_at);

        // Verify inventory was restored
        $inventoryItem = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first();
        $this->assertEquals(100, $inventoryItem->on_hand); // Back to original 100

        // Verify reversing GL journal
        $this->assertDatabaseHas('gl_journals', [
            'description' => 'Void Receipt #' . $receipt->id
        ]);
    }

    /** @test */
    public function sales_user_cannot_void_receipt()
    {
        $receipt = $this->createPostedReceipt();

        $response = $this->actingAs($this->salesUser, 'sanctum')
            ->patchJson("/api/receipts/{$receipt->id}/void", []);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'message' => 'Missing required capability: receipts.void'
            ]);
    }

    /** @test */
    public function cannot_void_draft_receipt()
    {
        $receipt = Receipt::factory()->create([
            'branch_id' => $this->branch->id,
            'status' => 'DRAFT'
        ]);

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->patchJson("/api/receipts/{$receipt->id}/void", []);

        $response->assertStatus(500); // Exception thrown
    }

    /** @test */
    public function test_foreign_key_constraint_violation()
    {
        $receiptData = $this->getValidReceiptData();
        $receiptData['branch_id'] = 'non-existent-uuid';

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/receipts', $receiptData);

        $response->assertStatus(500); // Foreign key violation
    }

    /** @test */
    public function test_unique_constraint_violation_receipt_number()
    {
        // Create first receipt
        $receipt1 = Receipt::factory()->create([
            'branch_id' => $this->branch->id,
            'number' => '20240831123456'
        ]);

        // Try to create another with same number
        $receiptData = $this->getValidReceiptData();
        $receiptData['number'] = '20240831123456';

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/receipts', $receiptData);

        $response->assertStatus(500); // Unique constraint violation
    }

    /** @test */
    public function test_check_constraint_violation_negative_quantity()
    {
        $receiptData = $this->getValidReceiptData();
        $receiptData['line_items'][0]['qty'] = -5; // Negative quantity

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/receipts', $receiptData);

        $response->assertStatus(500); // Check constraint violation
    }

    /** @test */
    public function test_insufficient_inventory_error()
    {
        $receiptData = $this->getValidReceiptData();
        $receiptData['line_items'][0]['qty'] = 200; // More than available (100)

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/receipts', $receiptData);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Not enough available stock to issue'
            ]);
    }

    /** @test */
    public function test_concurrent_inventory_update_with_locking()
    {
        // This test simulates concurrent access
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::transaction(function () {
            // First transaction locks the inventory item
            $inventoryItem = InventoryItem::where('product_id', $this->product->id)
                ->where('branch_id', $this->branch->id)
                ->lockForUpdate()
                ->first();

            // Simulate another process trying to access the same item
            // In a real scenario, this would be another request
            $anotherItem = InventoryItem::where('product_id', $this->product->id)
                ->where('branch_id', $this->branch->id)
                ->lockForUpdate()
                ->first();

            // This should cause a deadlock or lock timeout
        });
    }

    /** @test */
    public function test_idempotency_key_prevents_duplicate_requests()
    {
        $receiptData = $this->getValidReceiptData();
        $idempotencyKey = 'test-key-123';

        // First request
        $response1 = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/receipts', $receiptData);

        $response1->assertStatus(201);

        // Second request with same key
        $response2 = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/receipts', $receiptData);

        $response2->assertStatus(409)
            ->assertJson(['error' => 'Duplicate request']);
    }

    /** @test */
    public function test_partial_transaction_rollback_on_error()
    {
        // Create receipt data that will cause an error in the middle of processing
        $receiptData = $this->getValidReceiptData();
        $receiptData['line_items'][0]['qty'] = 200; // Will cause insufficient inventory error

        $initialInventory = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first()->on_hand;

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/receipts', $receiptData);

        $response->assertStatus(422);

        // Verify inventory was not changed (transaction rolled back)
        $finalInventory = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first()->on_hand;

        $this->assertEquals($initialInventory, $finalInventory);

        // Verify no receipt was created
        $this->assertDatabaseMissing('receipts', [
            'branch_id' => $this->branch->id
        ]);
    }

    /** @test */
    public function test_high_concurrency_simulation()
    {
        // Reduce available inventory to create contention
        $inventoryService = app(\App\Application\Services\InventoryService::class);
        $inventoryService->issueStock($this->product, $this->branch, 95); // Leave only 5

        $receiptData = $this->getValidReceiptData();
        $receiptData['line_items'][0]['qty'] = 3; // Small quantity to allow some concurrent requests

        $successfulRequests = 0;
        $failedRequests = 0;

        // Simulate multiple concurrent requests
        for ($i = 0; $i < 5; $i++) {
            try {
                $response = $this->actingAs($this->adminUser, 'sanctum')
                    ->postJson('/api/receipts', $receiptData);

                if ($response->getStatusCode() === 201) {
                    $successfulRequests++;
                } else {
                    $failedRequests++;
                }
            } catch (\Exception $e) {
                $failedRequests++;
            }
        }

        // At least one should succeed, some may fail due to insufficient stock
        $this->assertGreaterThan(0, $successfulRequests);
        $this->assertGreaterThanOrEqual(0, $failedRequests);
    }

    protected function getValidReceiptData()
    {
        return [
            'branch_id' => $this->branch->id,
            'currency' => 'ETB',
            'subtotal' => 500,
            'tax_total' => 50,
            'discount_total' => 25,
            'grand_total' => 525,
            'paid_total' => 525,
            'payment_method' => 'CASH',
            'line_items' => [
                [
                    'product_id' => $this->product->id,
                    'uom' => 'PCS',
                    'qty' => 5,
                    'price' => 100,
                    'discount' => 5,
                    'tax_rate' => 10,
                    'tax_amount' => 50,
                    'line_total' => 495,
                    'account_id' => 1,
                ]
            ]
        ];
    }

    protected function createPostedReceipt()
    {
        $receipt = Receipt::factory()->create([
            'branch_id' => $this->branch->id,
            'status' => 'POSTED'
        ]);

        // Create receipt line
        $receipt->lines()->create([
            'product_id' => $this->product->id,
            'uom' => 'PCS',
            'qty' => 5,
            'price' => 100,
            'discount' => 5,
            'tax_rate' => 10,
            'tax_amount' => 50,
            'line_total' => 495,
        ]);

        return $receipt;
    }
}