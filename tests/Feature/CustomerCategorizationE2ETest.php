<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Category;
use App\Models\Role;
use App\Models\Capability;
use App\Models\UserRoleAssignment;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class CustomerCategorizationE2ETest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $managerUser;
    protected $managerRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->managerRole = Role::create(['name' => 'Manager', 'slug' => 'manager', 'description' => 'Manager role']);

        // Create capabilities
        $manageCustomersCapability = Capability::create(['name' => 'manage_customers', 'key' => 'manage_customers', 'description' => 'Manage customers']);
        $viewCustomersCapability = Capability::create(['name' => 'customer.view', 'key' => 'customer.view', 'description' => 'View customers']);
        $createCustomersCapability = Capability::create(['name' => 'customer.create', 'key' => 'customer.create', 'description' => 'Create customers']);
        $viewCategoriesCapability = Capability::create(['name' => 'category.view', 'key' => 'category.view', 'description' => 'View categories']);
        $assignCategoriesCapability = Capability::create(['name' => 'category.assign', 'key' => 'category.assign', 'description' => 'Assign customers to categories']);

        // Assign capabilities to roles
        $this->managerRole->capabilities()->attach([
            $manageCustomersCapability->id,
            $viewCustomersCapability->id,
            $createCustomersCapability->id,
            $viewCategoriesCapability->id,
            $assignCategoriesCapability->id
        ]);

        // Create user
        $this->managerUser = User::factory()->create();

        // Assign role
        UserRoleAssignment::create([
            'user_id' => $this->managerUser->id,
            'role_id' => $this->managerRole->id
        ]);

        // Create user policies
        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'customer.view',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'customer.create',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'category.view',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'category.assign',
            'granted' => true
        ]);
    }

    /** @test */
    public function e2e_1_categorization_and_filtering()
    {
        // 1. Seed default categories
        $telebirrAgentCategory = Category::create([
            'name' => 'Telebirr Agent',
            'description' => 'Telebirr agents who distribute mobile money services',
            'customer_count' => 0,
        ]);

        $evoucherCategory = Category::create([
            'name' => 'eVoucher',
            'description' => 'Customers dealing with electronic vouchers',
            'customer_count' => 0,
        ]);

        $walkinCategory = Category::create([
            'name' => 'Walk-in',
            'description' => 'Walk-in customers visiting the branch',
            'customer_count' => 0,
        ]);

        $simCategory = Category::create([
            'name' => 'SIM',
            'description' => 'Customers purchasing SIM cards',
            'customer_count' => 0,
        ]);

        // 2. Register 5 customers; assign categories
        $customers = [
            [
                'type' => 'individual',
                'name' => 'Ahmed Hassan',
                'email' => 'ahmed.hassan@example.com',
                'phone' => '0912345678',
                'is_active' => true,
                'category_id' => $telebirrAgentCategory->id,
            ],
            [
                'type' => 'individual',
                'name' => 'Fatima Ali',
                'email' => 'fatima.ali@example.com',
                'phone' => '0912345679',
                'is_active' => true,
                'category_id' => $telebirrAgentCategory->id,
            ],
            [
                'type' => 'individual',
                'name' => 'Mohamed Omar',
                'email' => 'mohamed.omar@example.com',
                'phone' => '0912345680',
                'is_active' => true,
                'category_id' => $evoucherCategory->id,
            ],
            [
                'type' => 'individual',
                'name' => 'Amina Yusuf',
                'email' => 'amina.yusuf@example.com',
                'phone' => '0912345681',
                'is_active' => true,
                'category_id' => $walkinCategory->id,
            ],
            [
                'type' => 'individual',
                'name' => 'Hassan Ibrahim',
                'email' => 'hassan.ibrahim@example.com',
                'phone' => '0912345682',
                'is_active' => true,
                'category_id' => $simCategory->id,
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::create($customerData);
        }

        // Update category counts
        $telebirrAgentCategory->update(['customer_count' => 2]);
        $evoucherCategory->update(['customer_count' => 1]);
        $walkinCategory->update(['customer_count' => 1]);
        $simCategory->update(['customer_count' => 1]);

        // 3. Filter customer list by Telebirr Agent → expect only agents
        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/customers?category_id=' . $telebirrAgentCategory->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'name',
                        'email',
                        'phone',
                        'is_active',
                        'category_id'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total'
                ]
            ]);

        // Verify only Telebirr Agent customers are returned
        $responseData = $response->json('data');
        $this->assertCount(2, $responseData);

        foreach ($responseData as $customer) {
            $this->assertEquals($telebirrAgentCategory->id, $customer['category_id']);
        }

        // Verify the specific customers
        $customerNames = collect($responseData)->pluck('name')->sort()->values();
        $expectedNames = collect(['Ahmed Hassan', 'Fatima Ali'])->sort()->values();
        $this->assertEquals($expectedNames, $customerNames);
    }
}