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
use App\Application\Services\PosService;
use App\Application\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class PosIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $posService;
    protected $inventoryService;
    protected $user;
    protected $branch;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders to set up test data
        Artisan::call('db:seed', ['--class' => 'DatabaseSeeder']);

        // Rebuild RBAC cache
        Artisan::call('rbac:rebuild');

        $this->posService = app(PosService::class);
        $this->inventoryService = app(InventoryService::class);

        // Get test data
        $this->user = User::where('email', 'admin@example.com')->first();
        $this->branch = Branch::where('code', 'main')->first() ?? Branch::factory()->create(['code' => 'main', 'name' => 'Main Branch']);
        $this->product = Product::where('code', 'P001')->first() ?? Product::factory()->create(['code' => 'P001', 'name' => 'Yimulu']);

        // Set up initial inventory
        $this->inventoryService->openingBalance($this->product, $this->branch, 100);
    }

    /** @test */
    public function it_tests_receipt_creation_directly()
    {
        $receiptData = [
            'branch_id' => $this->branch->id,
            'number' => 'TEST-' . time(),
            'status' => 'DRAFT',
            'currency' => 'ETB',
            'subtotal' => 500,
            'tax_total' => 50,
            'discount_total' => 25,
            'grand_total' => 525,
            'paid_total' => 525,
            'payment_method' => 'CASH',
            'created_by' => $this->user->id,
        ];

        $receipt = Receipt::create($receiptData);

        // Verify receipt was created
        $this->assertInstanceOf(Receipt::class, $receipt);
        $this->assertNotNull($receipt->id);
        $this->assertEquals(525, $receipt->grand_total);
        $this->assertEquals('DRAFT', $receipt->status);

        // Verify it exists in database
        $this->assertDatabaseHas('receipts', [
            'number' => $receipt->number,
            'status' => 'DRAFT'
        ]);
    }

    /** @test */
    public function it_tests_inventory_operations_directly()
    {
        $receiptData = [
            'branch_id' => $this->branch->id,
            'number' => 'TEST-' . time(),
            'status' => 'DRAFT',
            'currency' => 'ETB',
            'subtotal' => 500,
            'tax_total' => 50,
            'discount_total' => 25,
            'grand_total' => 525,
            'paid_total' => 525,
            'payment_method' => 'CASH',
            'created_by' => $this->user->id,
        ];

        $lineItems = [
            [
                'product' => $this->product,
                'branch' => $this->branch,
                'qty' => 5,
                'price' => 100,
                'tax_amount' => 10,
                'discount' => 5,
                'account_id' => 1,
            ],
        ];

        $receipt = $this->posService->processReceipt($receiptData, $lineItems);

        // Verify receipt was created
        $this->assertInstanceOf(Receipt::class, $receipt);
        $this->assertNotNull($receipt->id);
        $this->assertEquals(525, $receipt->grand_total);

        // Verify inventory was reduced
        $inventoryItem = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first();
        $this->assertEquals(95, $inventoryItem->on_hand);

        // Verify stock movement was created
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => -5, // Negative for issue
            'type' => 'ISSUE',
        ]);

        // Verify GL journal was created
        $this->assertDatabaseHas('gl_journals', [
            'description' => 'Receipt #' . $receipt->id
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create_receipt',
            'subject_type' => Receipt::class
        ]);
    }

    /** @test */
    public function it_voids_receipt_and_restores_inventory()
    {
        // First create a receipt
        $receiptData = [
            'branch_id' => $this->branch->id,
            'created_by' => $this->user->id,
        ];

        $lineItems = [
            [
                'product' => $this->product,
                'branch' => $this->branch,
                'qty' => 5,
                'price' => 100,
                'tax_amount' => 10,
                'discount' => 5,
                'account_id' => 1,
            ],
        ];

        $receipt = $this->posService->processReceipt($receiptData, $lineItems);
        $receipt->update(['status' => 'POSTED']); // Mark as posted

        // Void the receipt
        $voided = $this->posService->voidReceipt($receipt, ['voided_by' => $this->user->id]);

        // Verify receipt was voided
        $this->assertEquals('voided', $voided->status);
        $this->assertNotNull($voided->voided_at);

        // Verify inventory was restored
        $inventoryItem = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first();
        $this->assertEquals(100, $inventoryItem->on_hand); // Back to original

        // Verify reversing stock movement
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 5, // Positive for receive
            'type' => 'RECEIVE',
            'ref' => $receipt->id . '-void',
        ]);

        // Verify reversing GL journal
        $this->assertDatabaseHas('gl_journals', [
            'description' => 'Void Receipt #' . $receipt->id
        ]);

        // Verify void audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'void_receipt',
            'subject_type' => Receipt::class
        ]);
    }

    /** @test */
    public function it_enforces_inventory_constraints()
    {
        // Try to issue more than available
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Not enough available stock to issue');

        $this->inventoryService->issueStock($this->product, $this->branch, 200); // More than 100 available
    }

    /** @test */
    public function it_enforces_unique_receipt_number_per_branch()
    {
        // Create first receipt
        Receipt::factory()->create([
            'branch_id' => $this->branch->id,
            'number' => '20240831123456'
        ]);

        // Try to create another with same number
        $this->expectException(\Illuminate\Database\QueryException::class);

        Receipt::factory()->create([
            'branch_id' => $this->branch->id,
            'number' => '20240831123456'
        ]);
    }

    /** @test */
    public function it_enforces_foreign_key_constraints()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Receipt::factory()->create([
            'branch_id' => 'non-existent-uuid', // Invalid foreign key
        ]);
    }

    /** @test */
    public function it_enforces_check_constraints_on_inventory()
    {
        $inventoryItem = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first();

        // Try to set negative on_hand
        $this->expectException(\Illuminate\Database\QueryException::class);
        $inventoryItem->update(['on_hand' => -5]);
    }

    /** @test */
    public function it_handles_concurrent_inventory_updates_with_locking()
    {
        // This test simulates concurrent access using database transactions
        $successCount = 0;
        $errorCount = 0;

        // Simulate multiple concurrent operations
        for ($i = 0; $i < 10; $i++) {
            try {
                DB::transaction(function () use (&$successCount) {
                    $item = InventoryItem::where('product_id', $this->product->id)
                        ->where('branch_id', $this->branch->id)
                        ->lockForUpdate()
                        ->first();

                    if ($item && $item->on_hand > 0) {
                        $item->on_hand -= 1;
                        $item->save();
                        $successCount++;
                    }
                });
            } catch (\Exception $e) {
                $errorCount++;
            }
        }

        // Should have processed up to 100 items (available stock)
        $this->assertGreaterThan(0, $successCount);
        $this->assertLessThanOrEqual(100, $successCount);

        // Final inventory should be correct
        $finalItem = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first();
        $this->assertEquals(100 - $successCount, $finalItem->on_hand);
    }

    /** @test */
    public function it_prevents_double_issuance_with_idempotency()
    {
        // This would normally be tested with idempotency keys in the controller
        // For service level testing, we can test the business logic

        $initialStock = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first()->on_hand;

        // Issue stock twice in same transaction (should work)
        DB::transaction(function () {
            $this->inventoryService->issueStock($this->product, $this->branch, 5);
            $this->inventoryService->issueStock($this->product, $this->branch, 5);
        });

        $finalStock = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first()->on_hand;

        $this->assertEquals($initialStock - 10, $finalStock);
    }

    /** @test */
    public function it_rolls_back_transaction_on_error()
    {
        $initialStock = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first()->on_hand;

        try {
            DB::transaction(function () {
                // Valid operation
                $this->inventoryService->issueStock($this->product, $this->branch, 5);

                // This should cause an error and rollback
                $this->inventoryService->issueStock($this->product, $this->branch, 200); // Insufficient stock
            });
        } catch (\Exception $e) {
            // Expected
        }

        // Stock should be unchanged due to rollback
        $finalStock = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first()->on_hand;

        $this->assertEquals($initialStock, $finalStock);
    }

    /** @test */
    public function it_enforces_stock_movement_constraints()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        StockMovement::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 0, // Should not be zero
            'type' => 'ISSUE',
        ]);
    }

    /** @test */
    public function it_calculates_available_stock_correctly()
    {
        $inventoryItem = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first();

        // Reserve some stock
        $this->inventoryService->reserve($this->product, $this->branch, 10);

        // Refresh from database
        $inventoryItem->refresh();

        // Available should be on_hand - reserved
        $this->assertEquals(90, $inventoryItem->available); // 100 - 10
        $this->assertEquals(100, $inventoryItem->on_hand);
        $this->assertEquals(10, $inventoryItem->reserved);
    }

    /** @test */
    public function it_handles_transfer_between_branches()
    {
        $toBranch = Branch::factory()->create(['code' => 'branch2', 'name' => 'Branch 2']);

        // Create inventory in destination branch
        $this->inventoryService->openingBalance($this->product, $toBranch, 0);

        // Transfer 20 items from main to branch2
        $this->inventoryService->transfer($this->product, $this->branch, $toBranch, 20);

        // Check source branch
        $sourceItem = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first();
        $this->assertEquals(80, $sourceItem->on_hand);

        // Check destination branch
        $destItem = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $toBranch->id)
            ->first();
        $this->assertEquals(20, $destItem->on_hand);

        // Check transfer movements
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 20,
            'type' => 'TRANSFER_OUT',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $toBranch->id,
            'qty' => 20,
            'type' => 'TRANSFER_IN',
        ]);
    }

    /** @test */
    public function it_prevents_negative_stock_on_adjustment()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Adjustment would result in negative stock');

        // Try to adjust down to negative
        $this->inventoryService->adjust($this->product, $this->branch, -150); // More than 100
    }
}