<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Capability;
use App\Models\UserRoleAssignment;
use App\Models\UserPolicy;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $adminUser;
    protected $managerUser;
    protected $regularUser;
    protected $adminRole;
    protected $managerRole;
    protected $regularRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Admin role']);
        $this->managerRole = Role::create(['name' => 'Manager', 'slug' => 'manager', 'description' => 'Manager role']);
        $this->regularRole = Role::create(['name' => 'User', 'slug' => 'user', 'description' => 'Regular user role']);

        // Create capabilities
        $viewCategoriesCapability = Capability::create(['name' => 'category.view', 'key' => 'category.view', 'description' => 'View categories']);
        $createCategoriesCapability = Capability::create(['name' => 'category.create', 'key' => 'category.create', 'description' => 'Create categories']);
        $updateCategoriesCapability = Capability::create(['name' => 'category.update', 'key' => 'category.update', 'description' => 'Update categories']);
        $deleteCategoriesCapability = Capability::create(['name' => 'category.delete', 'key' => 'category.delete', 'description' => 'Delete categories']);
        $assignCategoriesCapability = Capability::create(['name' => 'category.assign', 'key' => 'category.assign', 'description' => 'Assign customers to categories']);

        // Assign capabilities to roles
        $this->adminRole->capabilities()->attach([
            $viewCategoriesCapability->id,
            $createCategoriesCapability->id,
            $updateCategoriesCapability->id,
            $deleteCategoriesCapability->id,
            $assignCategoriesCapability->id
        ]);

        $this->managerRole->capabilities()->attach([
            $viewCategoriesCapability->id,
            $createCategoriesCapability->id,
            $updateCategoriesCapability->id,
            $assignCategoriesCapability->id
        ]);

        $this->regularRole->capabilities()->attach([
            $viewCategoriesCapability->id
        ]);

        // Create users
        $this->adminUser = User::factory()->create();
        $this->managerUser = User::factory()->create();
        $this->regularUser = User::factory()->create();

        // Assign roles and create user policies
        UserRoleAssignment::create([
            'user_id' => $this->adminUser->id,
            'role_id' => $this->adminRole->id
        ]);

        UserRoleAssignment::create([
            'user_id' => $this->managerUser->id,
            'role_id' => $this->managerRole->id
        ]);

        UserRoleAssignment::create([
            'user_id' => $this->regularUser->id,
            'role_id' => $this->regularRole->id
        ]);

        // Create user policies for direct capability checks
        UserPolicy::create([
            'user_id' => $this->adminUser->id,
            'capability_key' => 'category.view',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->adminUser->id,
            'capability_key' => 'category.create',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->adminUser->id,
            'capability_key' => 'category.update',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->adminUser->id,
            'capability_key' => 'category.delete',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->adminUser->id,
            'capability_key' => 'category.assign',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'category.view',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'category.create',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'category.update',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'category.assign',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->regularUser->id,
            'capability_key' => 'category.view',
            'granted' => true
        ]);
    }

    /** @test */
    public function admin_can_create_category()
    {
        $categoryData = [
            'name' => 'Premium Customers',
            'description' => 'High-value customers',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/categories', $categoryData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'customer_count',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertDatabaseHas('customer_categories', [
            'name' => 'Premium Customers',
            'description' => 'High-value customers',
            'customer_count' => 0
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'subject_type' => 'category'
        ]);
    }

    /** @test */
    public function manager_can_create_category()
    {
        $categoryData = [
            'name' => 'Regular Customers',
            'description' => 'Standard customers',
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/categories', $categoryData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Regular Customers',
                    'description' => 'Standard customers',
                    'customer_count' => 0
                ]
            ]);
    }

    /** @test */
    public function validation_errors_for_invalid_category_data()
    {
        $invalidData = [
            'name' => '', // Required
            'description' => str_repeat('a', 1001), // Too long
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/categories', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description']);
    }

    /** @test */
    public function cannot_create_category_with_duplicate_name()
    {
        // Create first category
        Category::create([
            'name' => 'Test Category',
            'description' => 'Test description'
        ]);

        $duplicateData = [
            'name' => 'Test Category', // Duplicate name
            'description' => 'Another description'
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/categories', $duplicateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function admin_can_list_categories_with_pagination()
    {
        // Create test categories
        Category::factory()->count(5)->create();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'customer_count',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function manager_can_list_categories()
    {
        Category::factory()->count(3)->create();

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function regular_user_can_list_categories()
    {
        Category::factory()->count(2)->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function can_retrieve_single_category_with_customers()
    {
        $category = Category::factory()->create();

        // Create customers in this category
        Customer::factory()->count(3)->create([
            'category_id' => $category->id
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'customer_count',
                    'customers' => [
                        '*' => [
                            'id',
                            'name',
                            'type',
                            'email',
                            'phone',
                            'is_active'
                        ]
                    ],
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJsonCount(3, 'data.customers');
    }

    /** @test */
    public function admin_can_update_category()
    {
        $category = Category::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original description'
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description'
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/categories/{$category->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated Name',
                    'description' => 'Updated description'
                ]
            ]);

        $this->assertDatabaseHas('customer_categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
            'description' => 'Updated description'
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update',
            'subject_type' => 'category',
            'subject_id' => $category->id
        ]);
    }

    /** @test */
    public function manager_can_update_category()
    {
        $category = Category::factory()->create([
            'name' => 'Manager Category',
            'description' => 'Manager description'
        ]);

        $updateData = [
            'name' => 'Updated by Manager',
            'description' => 'Updated description by manager'
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->patchJson("/api/categories/{$category->id}", $updateData);

        $response->assertStatus(200);
    }

    /** @test */
    public function regular_user_cannot_update_category()
    {
        $category = Category::factory()->create();

        $updateData = [
            'name' => 'Updated by User',
            'description' => 'Should not work'
        ];

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->patchJson("/api/categories/{$category->id}", $updateData);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_delete_empty_category()
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Category deleted successfully'
            ]);

        $this->assertDatabaseMissing('customer_categories', ['id' => $category->id]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'delete',
            'subject_type' => 'category',
            'subject_id' => $category->id
        ]);
    }

    /** @test */
    public function cannot_delete_category_with_customers()
    {
        $category = Category::factory()->create();

        // Add customers to category
        Customer::factory()->count(2)->create([
            'category_id' => $category->id
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete category with assigned customers. Please reassign customers first.'
            ]);

        $this->assertDatabaseHas('customer_categories', ['id' => $category->id]);
    }

    /** @test */
    public function manager_cannot_delete_category()
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function can_assign_customer_to_category()
    {
        $category = Category::factory()->create();
        $customer = Customer::factory()->create();

        $assignData = [
            'customer_id' => $customer->id,
            'category_id' => $category->id
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/categories/assign-customer', $assignData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Customer assigned to category successfully'
            ]);

        $customer->refresh();
        $this->assertEquals($category->id, $customer->category_id);

        $category->refresh();
        $this->assertEquals(1, $category->customer_count);
    }

    /** @test */
    public function can_remove_customer_from_category()
    {
        $category = Category::factory()->create();
        $customer = Customer::factory()->create([
            'category_id' => $category->id
        ]);

        $category->increment('customer_count');

        $removeData = [
            'customer_id' => $customer->id
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/categories/remove-customer', $removeData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Customer removed from category successfully'
            ]);

        $customer->refresh();
        $this->assertNull($customer->category_id);

        $category->refresh();
        $this->assertEquals(0, $category->customer_count);
    }

    /** @test */
    public function can_get_category_statistics()
    {
        Category::factory()->count(3)->create();
        Category::factory()->create(['customer_count' => 5]);
        Category::factory()->create(['customer_count' => 3]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/categories/stats');

        $response->assertStatus(200)
            ->assertJson([
                'total_categories' => 5,
                'categories_with_customers' => 2,
                'categories_without_customers' => 3,
                'total_customers_categorized' => 8
            ]);
    }

    /** @test */
    public function can_search_categories()
    {
        Category::factory()->create(['name' => 'Premium Customers', 'description' => 'High value']);
        Category::factory()->create(['name' => 'Basic Customers', 'description' => 'Regular customers']);
        Category::factory()->create(['name' => 'VIP Customers', 'description' => 'Very important']);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/categories?q=Premium');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/categories?q=customers');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function unauthenticated_user_cannot_access_category_endpoints()
    {
        $categoryData = [
            'name' => 'Test Category',
            'description' => 'Test description'
        ];

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(401);
    }

    /** @test */
    public function user_without_view_capability_cannot_list_categories()
    {
        $userWithoutCapability = User::factory()->create();

        $response = $this->actingAs($userWithoutCapability, 'sanctum')
            ->getJson('/api/categories');

        $response->assertStatus(403);
    }

    /** @test */
    public function user_without_create_capability_cannot_create_category()
    {
        $categoryData = [
            'name' => 'Test Category',
            'description' => 'Test description'
        ];

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/api/categories', $categoryData);

        $response->assertStatus(403);
    }
}