<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\Capability;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class ProductApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $managerUser;
    protected $regularUser;
    protected $managerRole;
    protected $regularRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->managerRole = Role::create(['name' => 'Manager', 'slug' => 'manager', 'description' => 'Manager role']);
        $this->regularRole = Role::create(['name' => 'Staff', 'slug' => 'staff', 'description' => 'Regular staff role']);

        // Create capabilities
        $manageCapability = Capability::create(['name' => 'products.manage', 'key' => 'products.manage', 'description' => 'Manage products']);
        $readCapability = Capability::create(['name' => 'products.read', 'key' => 'products.read', 'description' => 'Read products']);
        $createCapability = Capability::create(['name' => 'products.create', 'key' => 'products.create', 'description' => 'Create products']);
        $updateCapability = Capability::create(['name' => 'products.update', 'key' => 'products.update', 'description' => 'Update products']);

        // Assign capabilities to roles
        $this->managerRole->capabilities()->attach([
            $manageCapability->id,
            $readCapability->id,
            $createCapability->id,
            $updateCapability->id
        ]);

        $this->regularRole->capabilities()->attach([
            $readCapability->id,
            $createCapability->id,
            $updateCapability->id
        ]);

        // Create users
        $this->managerUser = User::factory()->create();
        $this->regularUser = User::factory()->create();

        // Assign roles
        UserRoleAssignment::create([
            'user_id' => $this->managerUser->id,
            'role_id' => $this->managerRole->id
        ]);

        UserRoleAssignment::create([
            'user_id' => $this->regularUser->id,
            'role_id' => $this->regularRole->id
        ]);
    }

    /** @test */
    public function manager_can_create_product()
    {
        $productData = [
            'code' => 'TEST001',
            'name' => 'Test Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true,
            'pricing_strategy' => 'FIXED',
            'description' => 'Test product description'
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/products', $productData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'code',
                'name',
                'type',
                'uom',
                'is_active',
                'pricing_strategy',
                'description'
            ]);

        $this->assertDatabaseHas('products', $productData);
    }

    /** @test */
    public function regular_user_cannot_create_product_without_manage_capability()
    {
        $productData = [
            'code' => 'TEST001',
            'name' => 'Test Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ];

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/api/products', $productData);

        $response->assertStatus(403);
    }

    /** @test */
    public function cannot_create_product_with_duplicate_code()
    {
        Product::create([
            'code' => 'TEST001',
            'name' => 'Existing Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        $productData = [
            'code' => 'TEST001',
            'name' => 'Duplicate Product',
            'type' => 'SERVICE',
            'uom' => 'PCS',
            'is_active' => true
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/products', $productData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    /** @test */
    public function can_list_products_with_filters()
    {
        Product::create([
            'code' => 'YIM001',
            'name' => 'Yimulu Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        Product::create([
            'code' => 'SVC001',
            'name' => 'Service Product',
            'type' => 'SERVICE',
            'uom' => 'HRS',
            'is_active' => true
        ]);

        // Test type filter
        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/products?type=YIMULU');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Test search filter
        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/products?q=Yimulu');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function can_update_product_without_stock()
    {
        $product = Product::create([
            'code' => 'TEST001',
            'name' => 'Test Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        $updateData = [
            'name' => 'Updated Product',
            'type' => 'SERVICE',
            'uom' => 'HRS'
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->patchJson("/api/products/{$product->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Product',
                'type' => 'SERVICE',
                'uom' => 'HRS'
            ]);
    }

    /** @test */
    public function cannot_change_product_type_when_stock_exists()
    {
        $product = Product::create([
            'code' => 'TEST001',
            'name' => 'Test Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB001',
            'is_active' => true
        ]);

        // Create inventory with stock
        InventoryItem::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'on_hand' => 10,
            'reserved' => 0
        ]);

        $updateData = [
            'type' => 'SERVICE'
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->patchJson("/api/products/{$product->id}", $updateData);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot change product type when stock exists'
            ]);
    }

    /** @test */
    public function can_update_product_type_when_no_stock_exists()
    {
        $product = Product::create([
            'code' => 'TEST001',
            'name' => 'Test Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB001',
            'is_active' => true
        ]);

        // Create inventory with zero stock
        InventoryItem::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'on_hand' => 0,
            'reserved' => 0
        ]);

        $updateData = [
            'type' => 'SERVICE'
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->patchJson("/api/products/{$product->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'SERVICE'
            ]);
    }

    /** @test */
    public function validation_errors_for_invalid_product_data()
    {
        $invalidData = [
            'code' => '', // required
            'name' => '', // required
            'type' => 'INVALID_TYPE', // invalid enum
            'uom' => str_repeat('A', 20), // too long
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/products', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name', 'type', 'uom']);
    }

    /** @test */
    public function can_delete_product_without_inventory()
    {
        $product = Product::create([
            'code' => 'TEST001',
            'name' => 'Test Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Product deleted successfully'
            ]);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /** @test */
    public function cannot_delete_product_with_inventory()
    {
        $product = Product::create([
            'code' => 'TEST001',
            'name' => 'Test Product',
            'type' => 'YIMULU',
            'uom' => 'PCS',
            'is_active' => true
        ]);

        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB001',
            'is_active' => true
        ]);

        InventoryItem::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'on_hand' => 5,
            'reserved' => 0
        ]);

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete product with existing inventory or receipts'
            ]);
    }
}