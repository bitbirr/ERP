<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\Role;
use App\Models\Capability;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $product;
    protected $branch;
    protected $inventoryItem;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user with necessary capabilities
        $role = Role::create(['name' => 'Inventory Manager', 'slug' => 'inventory-manager', 'description' => 'Can manage inventory']);

        $capabilities = [
            'inventory.read',
            'inventory.receive',
            'inventory.reserve',
            'inventory.unreserve',
            'inventory.issue',
            'inventory.transfer',
            'inventory.adjust'
        ];

        foreach ($capabilities as $capName) {
            $capability = Capability::create(['name' => $capName, 'key' => $capName, 'description' => $capName]);
            $role->capabilities()->attach($capability->id);
        }

        $this->user = User::factory()->create();
        UserRoleAssignment::create([
            'user_id' => $this->user->id,
            'role_id' => $role->id
        ]);

        // Create test data
        $this->product = Product::create([
            'code' => 'TEST001',
            'name' => 'Test Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB001',
            'is_active' => true
        ]);

        $this->inventoryItem = InventoryItem::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => 100,
            'reserved' => 0
        ]);
    }

    /** @test */
    public function can_get_inventory_item()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/inventory/{$this->branch->id}/{$this->product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->inventoryItem->id,
                'product_id' => $this->product->id,
                'branch_id' => $this->branch->id,
                'on_hand' => 100,
                'reserved' => 0,
                'available' => 100
            ]);
    }

    /** @test */
    public function returns_404_for_nonexistent_inventory_item()
    {
        $nonExistentProduct = Product::create([
            'code' => 'NONEXIST',
            'name' => 'Non-existent Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/inventory/{$this->branch->id}/{$nonExistentProduct->id}");

        $response->assertStatus(404)
            ->assertJson(['message' => 'Inventory item not found']);
    }

    /** @test */
    public function can_receive_stock()
    {
        $receiveData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 50,
            'ref' => 'RCV001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/receive', $receiveData);

        $response->assertStatus(200)
            ->assertJson([
                'on_hand' => 150,
                'reserved' => 0,
                'available' => 150
            ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 50,
            'type' => 'RECEIVE',
            'ref' => 'RCV001'
        ]);
    }

    /** @test */
    public function receive_is_idempotent_with_same_ref()
    {
        $receiveData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 50,
            'ref' => 'RCV001'
        ];

        // First receive
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/receive', $receiveData);

        // Second receive with same ref (should not duplicate)
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/receive', $receiveData);

        $response->assertStatus(200);

        // Should still have only 150 on_hand (not 200)
        $this->inventoryItem->refresh();
        $this->assertEquals(150, $this->inventoryItem->on_hand);

        // Should have only one RECEIVE movement
        $receiveMovements = StockMovement::where('ref', 'RCV001')
            ->where('type', 'RECEIVE')
            ->count();
        $this->assertEquals(1, $receiveMovements);
    }

    /** @test */
    public function cannot_receive_negative_quantity()
    {
        $receiveData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => -10,
            'ref' => 'RCV001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/receive', $receiveData);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Receive quantity cannot be negative']);
    }

    /** @test */
    public function can_reserve_stock()
    {
        $reserveData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 30,
            'ref' => 'RSV001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/reserve', $reserveData);

        $response->assertStatus(200)
            ->assertJson([
                'on_hand' => 100,
                'reserved' => 30,
                'available' => 70
            ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 30,
            'type' => 'RESERVE',
            'ref' => 'RSV001'
        ]);
    }

    /** @test */
    public function cannot_reserve_more_than_available()
    {
        $reserveData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 150,
            'ref' => 'RSV001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/reserve', $reserveData);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Not enough available stock to reserve']);
    }

    /** @test */
    public function can_unreserve_stock()
    {
        // First reserve some stock
        $this->inventoryItem->update(['reserved' => 20]);

        $unreserveData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 10,
            'ref' => 'UNRSV001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/unreserve', $unreserveData);

        $response->assertStatus(200)
            ->assertJson([
                'on_hand' => 100,
                'reserved' => 10,
                'available' => 90
            ]);
    }

    /** @test */
    public function can_issue_stock()
    {
        $issueData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 25,
            'ref' => 'ISS001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/issue', $issueData);

        $response->assertStatus(200)
            ->assertJson([
                'on_hand' => 75,
                'reserved' => 0,
                'available' => 75
            ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => -25,
            'type' => 'ISSUE',
            'ref' => 'ISS001'
        ]);
    }

    /** @test */
    public function cannot_issue_more_than_available()
    {
        $issueData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 150,
            'ref' => 'ISS001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/issue', $issueData);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Not enough available stock to issue']);
    }

    /** @test */
    public function can_transfer_stock_between_branches()
    {
        $toBranch = Branch::create([
            'name' => 'Destination Branch',
            'code' => 'TB002',
            'is_active' => true
        ]);

        $transferData = [
            'product_id' => $this->product->id,
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $toBranch->id,
            'qty' => 40,
            'ref' => 'TRF001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/transfer', $transferData);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Stock transferred successfully']);

        // Check source branch
        $this->inventoryItem->refresh();
        $this->assertEquals(60, $this->inventoryItem->on_hand);

        // Check destination branch
        $destInventory = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $toBranch->id)
            ->first();
        $this->assertEquals(40, $destInventory->on_hand);

        // Check movements
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 40,
            'type' => 'TRANSFER_OUT',
            'ref' => 'TRF001'
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $toBranch->id,
            'qty' => 40,
            'type' => 'TRANSFER_IN',
            'ref' => 'TRF001'
        ]);
    }

    /** @test */
    public function transfer_is_idempotent_with_same_ref()
    {
        $toBranch = Branch::create([
            'name' => 'Destination Branch',
            'code' => 'TB002',
            'is_active' => true
        ]);

        $transferData = [
            'product_id' => $this->product->id,
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $toBranch->id,
            'qty' => 40,
            'ref' => 'TRF001'
        ];

        // First transfer
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/transfer', $transferData);

        // Second transfer with same ref (should not duplicate)
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/transfer', $transferData);

        $response->assertStatus(200);

        // Should still have only 60 on_hand in source (not 20)
        $this->inventoryItem->refresh();
        $this->assertEquals(60, $this->inventoryItem->on_hand);
    }

    /** @test */
    public function can_adjust_stock()
    {
        $adjustData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 50, // Positive adjustment
            'ref' => 'ADJ001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/adjust', $adjustData);

        $response->assertStatus(200)
            ->assertJson([
                'on_hand' => 150,
                'reserved' => 0,
                'available' => 150
            ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 50,
            'type' => 'ADJUST',
            'ref' => 'ADJ001'
        ]);
    }

    /** @test */
    public function can_adjust_stock_negative()
    {
        $adjustData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => -30, // Negative adjustment
            'ref' => 'ADJ001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/adjust', $adjustData);

        $response->assertStatus(200)
            ->assertJson([
                'on_hand' => 70,
                'reserved' => 0,
                'available' => 70
            ]);
    }

    /** @test */
    public function cannot_adjust_to_negative_stock()
    {
        $adjustData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => -150, // Would result in negative stock
            'ref' => 'ADJ001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/adjust', $adjustData);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Adjustment would result in negative stock']);
    }

    /** @test */
    public function can_bulk_receive_stock()
    {
        $product2 = Product::create([
            'code' => 'TEST002',
            'name' => 'Test Product 2',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        $bulkData = [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 20,
                    'ref' => 'BRCV001'
                ],
                [
                    'product_id' => $product2->id,
                    'qty' => 30,
                    'ref' => 'BRCV002'
                ]
            ]
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/receive/bulk', $bulkData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Bulk receive completed successfully'
            ])
            ->assertJsonCount(2, 'results');

        // Check inventory updates
        $this->inventoryItem->refresh();
        $this->assertEquals(120, $this->inventoryItem->on_hand);

        $product2Inventory = InventoryItem::where('product_id', $product2->id)
            ->where('branch_id', $this->branch->id)
            ->first();
        $this->assertEquals(30, $product2Inventory->on_hand);
    }

    /** @test */
    public function bulk_receive_rolls_back_on_partial_failure()
    {
        $product2 = Product::create([
            'code' => 'TEST002',
            'name' => 'Test Product 2',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        $bulkData = [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 20,
                    'ref' => 'BRCV001'
                ],
                [
                    'product_id' => $product2->id,
                    'qty' => -10, // Invalid negative quantity
                    'ref' => 'BRCV002'
                ]
            ]
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/receive/bulk', $bulkData);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Bulk receive partially failed'
            ]);

        // Check that first item was not processed due to rollback
        $this->inventoryItem->refresh();
        $this->assertEquals(100, $this->inventoryItem->on_hand); // Should remain unchanged
    }

    /** @test */
    public function can_bulk_reserve_stock()
    {
        $product2 = Product::create([
            'code' => 'TEST002',
            'name' => 'Test Product 2',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        // Create inventory for second product
        InventoryItem::create([
            'product_id' => $product2->id,
            'branch_id' => $this->branch->id,
            'on_hand' => 50,
            'reserved' => 0
        ]);

        $bulkData = [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 20,
                    'ref' => 'BRSV001'
                ],
                [
                    'product_id' => $product2->id,
                    'qty' => 15,
                    'ref' => 'BRSV002'
                ]
            ]
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/reserve/bulk', $bulkData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Bulk reserve completed successfully'
            ]);

        // Check reservations
        $this->inventoryItem->refresh();
        $this->assertEquals(20, $this->inventoryItem->reserved);

        $product2Inventory = InventoryItem::where('product_id', $product2->id)
            ->where('branch_id', $this->branch->id)
            ->first();
        $this->assertEquals(15, $product2Inventory->reserved);
    }

    /** @test */
    public function validation_errors_for_invalid_inventory_data()
    {
        $invalidData = [
            'product_id' => 99999, // Non-existent
            'branch_id' => $this->branch->id,
            'qty' => 0, // Zero quantity
            'ref' => str_repeat('A', 300) // Too long
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/receive', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'qty', 'ref']);
    }

    /** @test */
    public function opening_balance_creates_inventory_item()
    {
        $newProduct = Product::create([
            'code' => 'NEW001',
            'name' => 'New Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        $openingData = [
            'product_id' => $newProduct->id,
            'branch_id' => $this->branch->id,
            'qty' => 100
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/opening', $openingData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('inventory_items', [
            'product_id' => $newProduct->id,
            'branch_id' => $this->branch->id,
            'on_hand' => 100,
            'reserved' => 0
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $newProduct->id,
            'branch_id' => $this->branch->id,
            'qty' => 100,
            'type' => 'OPENING'
        ]);
    }

    /** @test */
    public function opening_balance_is_idempotent()
    {
        $openingData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 50
        ];

        // First opening balance
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/opening', $openingData);

        // Second opening balance with same data (should not change)
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/opening', $openingData);

        $response->assertStatus(201);

        // Should still have 50 on_hand (not 100)
        $this->inventoryItem->refresh();
        $this->assertEquals(50, $this->inventoryItem->on_hand);
    }

    /** @test */
    public function cannot_reserve_stock_for_inactive_product()
    {
        // Deactivate the product
        $this->product->update(['is_active' => false]);

        $reserveData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 10,
            'ref' => 'RSV001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/reserve', $reserveData);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot reserve stock for inactive product']);
    }

    /** @test */
    public function cannot_issue_stock_for_inactive_product()
    {
        // Deactivate the product
        $this->product->update(['is_active' => false]);

        $issueData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 10,
            'ref' => 'ISS001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/issue', $issueData);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot issue stock for inactive product']);
    }

    /** @test */
    public function cannot_transfer_stock_for_inactive_product()
    {
        // Deactivate the product
        $this->product->update(['is_active' => false]);

        $toBranch = Branch::create([
            'name' => 'Destination Branch',
            'code' => 'TB002',
            'is_active' => true
        ]);

        $transferData = [
            'product_id' => $this->product->id,
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $toBranch->id,
            'qty' => 10,
            'ref' => 'TRF001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/transfer', $transferData);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot transfer stock for inactive product']);
    }

    /** @test */
    public function reserve_returns_409_for_concurrent_conflict()
    {
        // First reserve some stock to reduce available
        $this->inventoryItem->update(['reserved' => 90]); // Only 10 available

        $reserveData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 20, // More than available
            'ref' => 'RSV001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/reserve', $reserveData);

        $response->assertStatus(409)
            ->assertJson(['message' => 'Not enough available stock to reserve - concurrent request conflict']);
    }

    /** @test */
    public function issue_returns_409_for_concurrent_conflict()
    {
        // First reserve some stock to reduce available
        $this->inventoryItem->update(['reserved' => 90]); // Only 10 available

        $issueData = [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 20, // More than available
            'ref' => 'ISS001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/issue', $issueData);

        $response->assertStatus(409)
            ->assertJson(['message' => 'Not enough available stock to issue - concurrent request conflict']);
    }

    /** @test */
    public function transfer_returns_409_for_concurrent_conflict()
    {
        // First reserve most of the stock to reduce available
        $this->inventoryItem->update(['reserved' => 90]); // Only 10 available

        $toBranch = Branch::create([
            'name' => 'Destination Branch',
            'code' => 'TB002',
            'is_active' => true
        ]);

        $transferData = [
            'product_id' => $this->product->id,
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $toBranch->id,
            'qty' => 20, // More than available
            'ref' => 'TRF001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/transfer', $transferData);

        $response->assertStatus(409)
            ->assertJson(['message' => 'Not enough available stock to transfer - concurrent request conflict']);
    }

    /** @test */
    public function bulk_reserve_returns_409_when_concurrency_conflicts_occur()
    {
        $product2 = Product::create([
            'code' => 'TEST002',
            'name' => 'Test Product 2',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        // Create inventory for second product with limited stock
        InventoryItem::create([
            'product_id' => $product2->id,
            'branch_id' => $this->branch->id,
            'on_hand' => 50,
            'reserved' => 40 // Only 10 available
        ]);

        $bulkData = [
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 20, // More than available (only 100 total, but let's say concurrent access)
                    'ref' => 'BRSV001'
                ],
                [
                    'product_id' => $product2->id,
                    'qty' => 5, // This should succeed
                    'ref' => 'BRSV002'
                ]
            ]
        ];

        // Simulate concurrency by reserving most of first product
        $this->inventoryItem->update(['reserved' => 90]); // Only 10 available

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/reserve/bulk', $bulkData);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Bulk reserve partially failed'
            ])
            ->assertJsonStructure([
                'successful',
                'failed'
            ]);
    }

    /** @test */
    public function can_still_read_inventory_for_inactive_product()
    {
        // Deactivate the product
        $this->product->update(['is_active' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/inventory/{$this->branch->id}/{$this->product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->inventoryItem->id,
                'product_id' => $this->product->id,
                'branch_id' => $this->branch->id,
                'on_hand' => 100,
                'reserved' => 0,
                'available' => 100
            ]);
    }
}