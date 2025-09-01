<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Application\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class BranchTransferTest extends TestCase
{
    use RefreshDatabase;

    protected $inventoryService;
    protected $mainBranch;
    protected $hamdaBranch;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = app(InventoryService::class);

        // Create branches
        $this->mainBranch = Branch::create(['name' => 'Main Branch', 'code' => 'MAIN']);
        $this->hamdaBranch = Branch::create(['name' => 'Hamda Branch', 'code' => 'HAMDA']);

        // Create product
        $this->product = Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'code' => 'TEST001',
            'name' => 'Test Product',
            'type' => 'inventory',
            'uom' => 'pcs',
            'price' => 10.00,
            'cost' => 8.00,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_transfers_stock_from_main_to_hamda_branch()
    {
        // Setup: Add initial stock to main branch
        $this->inventoryService->openingBalance($this->product, $this->mainBranch, 200);

        // Verify initial state
        $mainInventory = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->mainBranch->id)
            ->first();
        $this->assertEquals(200, $mainInventory->on_hand);

        $hamdaInventory = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->hamdaBranch->id)
            ->first();
        $this->assertNull($hamdaInventory); // Should not exist yet

        // Action: Transfer 100 units from main to hamda
        $this->inventoryService->transfer(
            $this->product,
            $this->mainBranch,
            $this->hamdaBranch,
            100,
            'transfer-test-001'
        );

        // Verify transfer results
        $mainInventory->refresh();
        $this->assertEquals(100, $mainInventory->on_hand); // 200 - 100 = 100

        $hamdaInventory = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->hamdaBranch->id)
            ->first();
        $this->assertNotNull($hamdaInventory);
        $this->assertEquals(100, $hamdaInventory->on_hand); // New inventory created with 100

        // Verify total conservation: 100 (main) + 100 (hamda) = 200 (original total)
        $totalStock = InventoryItem::where('product_id', $this->product->id)
            ->sum('on_hand');
        $this->assertEquals(200, $totalStock);

        // Verify stock movements
        $transferOutMovement = StockMovement::where('product_id', $this->product->id)
            ->where('branch_id', $this->mainBranch->id)
            ->where('type', 'TRANSFER_OUT')
            ->where('ref', 'transfer-test-001')
            ->first();
        $this->assertNotNull($transferOutMovement);
        $this->assertEquals(100, $transferOutMovement->qty);

        $transferInMovement = StockMovement::where('product_id', $this->product->id)
            ->where('branch_id', $this->hamdaBranch->id)
            ->where('type', 'TRANSFER_IN')
            ->where('ref', 'transfer-test-001')
            ->first();
        $this->assertNotNull($transferInMovement);
        $this->assertEquals(100, $transferInMovement->qty);
    }

    /** @test */
    public function it_prevents_transfer_when_insufficient_stock()
    {
        // Setup: Add only 50 units to main branch
        $this->inventoryService->openingBalance($this->product, $this->mainBranch, 50);

        // Action & Assert: Try to transfer 100 units (should fail)
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Not enough available stock to transfer');

        $this->inventoryService->transfer(
            $this->product,
            $this->mainBranch,
            $this->hamdaBranch,
            100
        );
    }

    /** @test */
    public function it_prevents_transfer_to_same_branch()
    {
        // Action & Assert: Try to transfer to same branch (should fail)
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Cannot transfer to the same branch');

        $this->inventoryService->transfer(
            $this->product,
            $this->mainBranch,
            $this->mainBranch,
            100
        );
    }

    /** @test */
    public function it_supports_idempotent_transfers()
    {
        // Setup: Add initial stock
        $this->inventoryService->openingBalance($this->product, $this->mainBranch, 200);

        // First transfer
        $this->inventoryService->transfer(
            $this->product,
            $this->mainBranch,
            $this->hamdaBranch,
            50,
            'idempotent-test-001'
        );

        // Second transfer with same reference (should be idempotent - no change)
        $this->inventoryService->transfer(
            $this->product,
            $this->mainBranch,
            $this->hamdaBranch,
            50,
            'idempotent-test-001'
        );

        // Verify only one transfer occurred
        $mainInventory = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->mainBranch->id)
            ->first();
        $this->assertEquals(150, $mainInventory->on_hand); // 200 - 50 = 150

        $hamdaInventory = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->hamdaBranch->id)
            ->first();
        $this->assertEquals(50, $hamdaInventory->on_hand);

        // Verify only one set of movements created
        $transferOutMovements = StockMovement::where('product_id', $this->product->id)
            ->where('branch_id', $this->mainBranch->id)
            ->where('type', 'TRANSFER_OUT')
            ->where('ref', 'idempotent-test-001')
            ->count();
        $this->assertEquals(1, $transferOutMovements);
    }

}