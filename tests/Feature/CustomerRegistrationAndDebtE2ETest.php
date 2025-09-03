<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Capability;
use App\Models\UserRoleAssignment;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class CustomerRegistrationAndDebtE2ETest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $managerUser;
    protected $managerRole;
    protected $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->managerRole = Role::create(['name' => 'Manager', 'slug' => 'manager', 'description' => 'Manager role']);

        // Create capabilities
        $manageCustomersCapability = Capability::create(['name' => 'manage_customers', 'key' => 'manage_customers', 'description' => 'Manage customers']);
        $viewCustomersCapability = Capability::create(['name' => 'customer.view', 'key' => 'customer.view', 'description' => 'View customers']);
        $createCustomersCapability = Capability::create(['name' => 'customer.create', 'key' => 'customer.create', 'description' => 'Create customers']);
        $viewReceiptsCapability = Capability::create(['name' => 'receipts.view', 'key' => 'receipts.view', 'description' => 'View receipts']);
        $createReceiptsCapability = Capability::create(['name' => 'receipts.create', 'key' => 'receipts.create', 'description' => 'Create receipts']);
        $updateCustomersCapability = Capability::create(['name' => 'customer.update', 'key' => 'customer.update', 'description' => 'Update customers']);
        $viewContactsCapability = Capability::create(['name' => 'customer_contact.view', 'key' => 'customer_contact.view', 'description' => 'View customer contacts']);

        // Assign capabilities to roles
        $this->managerRole->capabilities()->attach([
            $manageCustomersCapability->id,
            $viewCustomersCapability->id,
            $createCustomersCapability->id,
            $viewReceiptsCapability->id,
            $createReceiptsCapability->id,
            $updateCustomersCapability->id,
            $viewContactsCapability->id
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
            'capability_key' => 'receipts.view',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'receipts.create',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'customer.update',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'customer_contact.view',
            'granted' => true
        ]);

        // Create a branch for receipts
        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'address' => '123 Main St',
            'phone' => '+251911234567',
            'is_active' => true
        ]);

        // Create a simple product for receipt lines
        $this->product = \App\Models\Product::create([
            'code' => 'TEST-001',
            'name' => 'Test Product',
            'type' => 'service',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function e2e_2_customer_registration_and_debt()
    {
        // 1. Register new customer with primary + second phone
        $customerData = [
            'type' => 'individual',
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+251911234567', // Primary phone
            'is_active' => true,
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
                    'is_active'
                ]
            ]);

        $customer = Customer::find($response->json('data.id'));
        $this->assertEquals('+251911234567', $customer->phone);

        // Add second phone as contact
        $contactData = [
            'type' => 'phone',
            'value' => '+251922345678', // Second phone
            'is_primary' => false,
            'label' => 'Mobile'
        ];

        $contactResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson("/api/customers/{$customer->id}/contacts", $contactData);

        $contactResponse->assertStatus(201);

        // Refresh customer with contacts
        $customer->refresh();
        $customer->load('contacts');
        $this->assertCount(1, $customer->contacts);
        $this->assertEquals('+251922345678', $customer->contacts->first()->value);

        // 2. Create completed orders and one unpaid invoice → pending_debt updates
        // Create completed order (fully paid)
        $completedOrder = Receipt::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'branch_id' => $this->branch->id,
            'number' => 'RCP-001',
            'status' => 'POSTED',
            'customer_id' => $customer->id,
            'currency' => 'ETB',
            'subtotal' => 1000.00,
            'tax_total' => 150.00,
            'discount_total' => 0.00,
            'grand_total' => 1150.00,
            'paid_total' => 1150.00, // Fully paid
            'payment_method' => 'CASH',
            'posted_at' => now(),
            'created_by' => $this->managerUser->id,
            'posted_by' => $this->managerUser->id,
        ]);

        // Create receipt line for the completed order
        ReceiptLine::create([
            'receipt_id' => $completedOrder->id,
            'product_id' => $this->product->id,
            'uom' => 'each',
            'qty' => 1,
            'price' => 1000.00,
            'discount' => 0.00,
            'tax_rate' => 15.00,
            'tax_amount' => 150.00,
            'line_total' => 1000.00,
            'meta' => json_encode(['description' => 'Service Charge']),
        ]);

        // Create another completed order (fully paid)
        $completedOrder2 = Receipt::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'branch_id' => $this->branch->id,
            'number' => 'RCP-002',
            'status' => 'POSTED',
            'customer_id' => $customer->id,
            'currency' => 'ETB',
            'subtotal' => 500.00,
            'tax_total' => 75.00,
            'discount_total' => 0.00,
            'grand_total' => 575.00,
            'paid_total' => 575.00, // Fully paid
            'payment_method' => 'CASH',
            'posted_at' => now(),
            'created_by' => $this->managerUser->id,
            'posted_by' => $this->managerUser->id,
        ]);

        // Create receipt line for the second completed order
        ReceiptLine::create([
            'receipt_id' => $completedOrder2->id,
            'product_id' => $this->product->id,
            'uom' => 'each',
            'qty' => 1,
            'price' => 500.00,
            'discount' => 0.00,
            'tax_rate' => 15.00,
            'tax_amount' => 75.00,
            'line_total' => 500.00,
            'meta' => json_encode(['description' => 'Consultation Fee']),
        ]);

        // Create unpaid invoice
        $unpaidInvoice = Receipt::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'branch_id' => $this->branch->id,
            'number' => 'INV-001',
            'status' => 'POSTED',
            'customer_id' => $customer->id,
            'currency' => 'ETB',
            'subtotal' => 2000.00,
            'tax_total' => 300.00,
            'discount_total' => 0.00,
            'grand_total' => 2300.00,
            'paid_total' => 1000.00, // Partially paid - 1300 unpaid
            'payment_method' => 'CASH',
            'posted_at' => now(),
            'created_by' => $this->managerUser->id,
            'posted_by' => $this->managerUser->id,
        ]);

        // Create receipt line for the unpaid invoice
        ReceiptLine::create([
            'receipt_id' => $unpaidInvoice->id,
            'product_id' => $this->product->id,
            'uom' => 'each',
            'qty' => 1,
            'price' => 2000.00,
            'discount' => 0.00,
            'tax_rate' => 15.00,
            'tax_amount' => 300.00,
            'line_total' => 2000.00,
            'meta' => json_encode(['description' => 'Equipment Purchase']),
        ]);

        // 3. Fetch /customers/{id}/orders shows history
        $ordersResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson("/api/customers/{$customer->id}/orders");

        $ordersResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'number',
                        'status',
                        'customer_id',
                        'grand_total',
                        'paid_total',
                        'created_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total'
                ]
            ]);

        $ordersData = $ordersResponse->json('data');
        $this->assertCount(3, $ordersData); // 2 completed orders + 1 unpaid invoice

        // Verify order details
        $orderNumbers = collect($ordersData)->pluck('number')->sort()->values()->toArray();
        $expectedNumbers = ['INV-001', 'RCP-001', 'RCP-002'];
        $this->assertEquals($expectedNumbers, $orderNumbers);

        // 4. /pending-debt matches AR outstanding
        $debtResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson("/api/customers/{$customer->id}/pending-debt");

        $debtResponse->assertStatus(200)
            ->assertJsonStructure([
                'customer_id',
                'pending_debt',
                'currency'
            ]);

        $pendingDebt = $debtResponse->json('pending_debt');
        $this->assertEquals(1300.00, $pendingDebt); // 2300 - 1000 = 1300

        // Verify the pending debt matches the calculation
        $customer->refresh();
        $this->assertEquals(1300.00, $customer->pending_debt);
    }
}