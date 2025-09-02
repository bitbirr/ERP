<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerAddress;
use App\Models\CustomerInteraction;
use App\Models\Role;
use App\Models\Capability;
use App\Models\UserRoleAssignment;
use App\Models\UserPolicy;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class CustomerApiTest extends TestCase
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
        $manageCustomersCapability = Capability::create(['name' => 'manage_customers', 'key' => 'manage_customers', 'description' => 'Manage customers']);
        $viewCustomersCapability = Capability::create(['name' => 'customer.view', 'key' => 'customer.view', 'description' => 'View customers']);
        $createCustomersCapability = Capability::create(['name' => 'customer.create', 'key' => 'customer.create', 'description' => 'Create customers']);
        $updateCustomersCapability = Capability::create(['name' => 'customer.update', 'key' => 'customer.update', 'description' => 'Update customers']);
        $deleteCustomersCapability = Capability::create(['name' => 'customer.delete', 'key' => 'customer.delete', 'description' => 'Delete customers']);

        // Assign capabilities to roles
        $this->managerRole->capabilities()->attach([
            $manageCustomersCapability->id,
            $viewCustomersCapability->id,
            $createCustomersCapability->id,
            $updateCustomersCapability->id,
            $deleteCustomersCapability->id
        ]);

        $this->regularRole->capabilities()->attach([
            $viewCustomersCapability->id,
            $createCustomersCapability->id,
            $updateCustomersCapability->id,
            $deleteCustomersCapability->id
        ]);

        // Create users
        $this->managerUser = User::factory()->create();
        $this->regularUser = User::factory()->create();

        // Assign roles and create user policies
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
            'user_id' => $this->managerUser->id,
            'capability_key' => 'manage_customers',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'customer.view',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->regularUser->id,
            'capability_key' => 'customer.view',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->regularUser->id,
            'capability_key' => 'customer.create',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->regularUser->id,
            'capability_key' => 'customer.update',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->regularUser->id,
            'capability_key' => 'customer.delete',
            'granted' => true
        ]);
    }

    /** @test */
    public function manager_can_create_individual_customer()
    {
        $customerData = [
            'type' => 'individual',
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '0912345678',
            'is_active' => true,
            'description' => 'Test individual customer'
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'name',
                    'email',
                    'phone',
                    'is_active',
                    'description',
                    'contacts',
                    'addresses'
                ]
            ]);

        $this->assertDatabaseHas('customers', [
            'type' => 'individual',
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+251912345678', // Normalized phone
            'is_active' => true
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'subject_type' => 'customer'
        ]);
    }

    /** @test */
    public function manager_can_create_organization_customer()
    {
        $customerData = [
            'type' => 'organization',
            'name' => 'ABC Corporation',
            'email' => 'contact@abc-corp.com',
            'phone' => '+251911234567',
            'tax_id' => '123456789',
            'is_active' => true,
            'description' => 'Test organization customer'
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/customers', $customerData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'type' => 'organization',
                    'name' => 'ABC Corporation',
                    'email' => 'contact@abc-corp.com',
                    'phone' => '+251911234567',
                    'tax_id' => '123456789'
                ]
            ]);

        $this->assertDatabaseHas('customers', $customerData);
    }

    /** @test */
    public function phone_number_is_normalized_during_creation()
    {
        $customerData = [
            'type' => 'individual',
            'name' => 'Test User',
            'phone' => '0912345678' // Ethiopian format without +251
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/customers', $customerData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'name' => 'Test User',
            'phone' => '+251912345678' // Should be normalized
        ]);
    }

    /** @test */
    public function validation_errors_for_invalid_customer_data()
    {
        $invalidData = [
            'type' => 'invalid_type', // Invalid enum
            'name' => '', // Required
            'email' => 'invalid-email', // Invalid email format
            'phone' => str_repeat('1', 21), // Too long
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/customers', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'name', 'email', 'phone']);
    }

    /** @test */
    public function cannot_create_customer_with_duplicate_email()
    {
        // Create first customer
        Customer::create([
            'type' => 'individual',
            'name' => 'Existing User',
            'email' => 'test@example.com'
        ]);

        $duplicateData = [
            'type' => 'individual',
            'name' => 'New User',
            'email' => 'test@example.com' // Duplicate email
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/customers', $duplicateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function can_list_customers_with_pagination()
    {
        // Create test customers
        Customer::factory()->count(5)->create();

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'name',
                        'email',
                        'is_active'
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
    public function can_retrieve_single_customer_with_relationships()
    {
        $customer = Customer::factory()->create();

        // Create related data
        CustomerContact::factory()->create([
            'customer_id' => $customer->id,
            'type' => 'email',
            'value' => 'contact@example.com'
        ]);

        CustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'street_address' => '123 Main St',
            'city' => 'Addis Ababa'
        ]);

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'name',
                    'contacts' => [
                        '*' => [
                            'id',
                            'type',
                            'value'
                        ]
                    ],
                    'addresses' => [
                        '*' => [
                            'id',
                            'street_address',
                            'city'
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function can_update_customer_information()
    {
        $customer = Customer::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com'
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'description' => 'Updated description'
        ];

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->patchJson("/api/customers/{$customer->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com',
                    'description' => 'Updated description'
                ]
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update',
            'subject_type' => 'customer',
            'subject_id' => $customer->id
        ]);
    }

    /** @test */
    public function can_delete_customer_without_interactions()
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Customer deleted successfully'
            ]);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'delete',
            'subject_type' => 'customer',
            'subject_id' => $customer->id
        ]);
    }

    /** @test */
    public function cannot_delete_customer_with_existing_interactions()
    {
        $customer = Customer::factory()->create();

        // Create an interaction for the customer
        CustomerInteraction::factory()->create([
            'customer_id' => $customer->id,
            'type' => 'call',
            'description' => 'Test interaction'
        ]);

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete customer with existing interactions'
            ]);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    /** @test */
    public function can_search_customers_with_filters()
    {
        // Create test customers
        Customer::factory()->create([
            'type' => 'individual',
            'name' => 'John Smith',
            'email' => 'john@example.com'
        ]);

        Customer::factory()->create([
            'type' => 'organization',
            'name' => 'Smith Corp',
            'email' => 'contact@smithcorp.com'
        ]);

        // Test search by name
        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/customers?q=Smith');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Test filter by type
        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/customers?type=individual');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function user_without_manage_customers_capability_cannot_create_customer()
    {
        $customerData = [
            'type' => 'individual',
            'name' => 'Test Customer',
            'email' => 'test@example.com'
        ];

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/api/customers', $customerData);

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_customer_endpoints()
    {
        $customerData = [
            'type' => 'individual',
            'name' => 'Test Customer'
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(401);
    }
}