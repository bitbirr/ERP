<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Capability;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use App\Application\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected $inventoryService;
    protected $mainBranch;
    protected $product;
    protected $userWithCapability;
    protected $userWithoutCapability;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = app(InventoryService::class);

        // Create branch
        $this->mainBranch = Branch::create(['name' => 'Main Branch', 'code' => 'MAIN']);

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

        // Create capability
        $inventoryAdjustCapability = Capability::create([
            'name' => 'Inventory Adjust',
            'key' => 'inventory.adjust',
            'group' => 'inventory'
        ]);

        // Create role with capability
        $role = Role::create(['name' => 'Inventory Manager', 'slug' => 'inventory_manager']);
        $role->capabilities()->attach($inventoryAdjustCapability);

        // Create users
        $this->userWithCapability = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Inventory Manager',
            'email' => 'manager@test.com',
            'password' => bcrypt('password')
        ]);

        $this->userWithoutCapability = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'password' => bcrypt('password')
        ]);

        // Assign role to user with capability
        UserRoleAssignment::create([
            'user_id' => $this->userWithCapability->id,
            'role_id' => $role->id,
            'branch_id' => $this->mainBranch->id
        ]);
    }

    /** @test */
    public function it_adjusts_inventory_for_shrinkage_with_reason_damaged()
    {
        // Setup: Add initial stock
        $this->inventoryService->openingBalance($this->product, $this->mainBranch, 100);

        // Verify initial state
        $inventory = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->mainBranch->id)
            ->first();
        $this->assertEquals(100, $inventory->on_hand);

        // Mock authenticated user with capability
        $this->mock(\Illuminate\Contracts\Auth\Guard::class, function ($mock) {
            $mock->shouldReceive('user')->andReturn($this->userWithCapability);
        });

        // Action: Adjust for shrinkage (-3 units) with reason "damaged"
        $adjustedInventory = $this->inventoryService->adjust(
            $this->product,
            $this->mainBranch,
            -3,
            'damaged',
            'shrinkage-test-001'
        );

        // Verify adjustment results
        $this->assertEquals(97, $adjustedInventory->on_hand); // 100 - 3 = 97

        // Verify stock movement was created with reason
        $movement = StockMovement::where('product_id', $this->product->id)
            ->where('branch_id', $this->mainBranch->id)
            ->where('type', 'ADJUST')
            ->where('ref', 'shrinkage-test-001')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(-3, $movement->qty);
        $this->assertEquals('damaged', $movement->meta['reason']);
    }

    /** @test */
    public function it_requires_inventory_adjust_capability()
    {
        // Setup: Add initial stock
        $this->inventoryService->openingBalance($this->product, $this->mainBranch, 100);

        // Action: Try to adjust without capability (should fail)
        $this->actingAs($this->userWithoutCapability);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Insufficient permissions to adjust inventory');

        $this->inventoryService->adjust(
            $this->product,
            $this->mainBranch,
            -3,
            'damaged'
        );
    }

    /** @test */
    public function it_prevents_negative_stock_from_adjustment()
    {
        // Setup: Add initial stock of 5
        $this->actingAs($this->userWithCapability);
        $this->inventoryService->openingBalance($this->product, $this->mainBranch, 5);

        // Action: Try to adjust by -10 (would result in negative stock)
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Adjustment would result in negative stock');

        $this->inventoryService->adjust(
            $this->product,
            $this->mainBranch,
            -10,
            'damaged'
        );
    }

    /** @test */
    public function it_supports_idempotent_adjustments()
    {
        // Setup: Add initial stock
        $this->actingAs($this->userWithCapability);
        $this->inventoryService->openingBalance($this->product, $this->mainBranch, 100);

        // First adjustment
        $this->inventoryService->adjust(
            $this->product,
            $this->mainBranch,
            -3,
            'damaged',
            'idempotent-adjust-001'
        );

        // Second adjustment with same reference (should be idempotent - no change)
        $this->inventoryService->adjust(
            $this->product,
            $this->mainBranch,
            -3,
            'damaged',
            'idempotent-adjust-001'
        );

        // Verify only one adjustment occurred
        $inventory = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->mainBranch->id)
            ->first();
        $this->assertEquals(97, $inventory->on_hand); // 100 - 3 = 97

        // Verify only one movement created
        $movements = StockMovement::where('product_id', $this->product->id)
            ->where('branch_id', $this->mainBranch->id)
            ->where('type', 'ADJUST')
            ->where('ref', 'idempotent-adjust-001')
            ->count();
        $this->assertEquals(1, $movements);
    }

    /** @test */
    public function it_adjusts_inventory_via_api_endpoint()
    {
        // Setup: Add initial stock
        Auth::login($this->userWithCapability);
        $this->inventoryService->openingBalance($this->product, $this->mainBranch, 100);

        // Action: Make API request to adjust inventory
        $response = $this->actingAs($this->userWithCapability)->postJson('/api/inventory/adjust', [
            'product_id' => $this->product->id,
            'branch_id' => $this->mainBranch->id,
            'qty' => -3,
            'reason' => 'damaged',
            'ref' => 'api-adjust-test-001'
        ]);

        // Assert response
        $response->assertStatus(200)
                ->assertJson([
                    'on_hand' => 97
                ]);

        // Verify adjustment in database
        $inventory = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->mainBranch->id)
            ->first();
        $this->assertEquals(97, $inventory->on_hand);

        // Verify movement with reason
        $movement = StockMovement::where('ref', 'api-adjust-test-001')->first();
        $this->assertEquals('damaged', $movement->meta['reason']);
    }
}