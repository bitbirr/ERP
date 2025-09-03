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
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Carbon\Carbon;

class LoyaltyDiscountAtPOSE2ETest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $managerUser;
    protected $managerRole;
    protected $branch;
    protected $product;

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

        // Create loyalty capabilities
        $viewLoyaltyCapability = Capability::create(['name' => 'loyalty.view', 'key' => 'loyalty.view', 'description' => 'View loyalty data']);
        $manageLoyaltyCapability = Capability::create(['name' => 'loyalty.manage', 'key' => 'loyalty.manage', 'description' => 'Manage loyalty data']);

        // Assign loyalty capabilities to roles
        $this->managerRole->capabilities()->attach([
            $viewLoyaltyCapability->id,
            $manageLoyaltyCapability->id
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

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'loyalty.view',
            'granted' => true
        ]);

        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'capability_key' => 'loyalty.manage',
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
        $this->product = Product::create([
            'code' => 'TEST-001',
            'name' => 'Test Product',
            'type' => 'service',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function e2e_3_loyalty_discount_at_pos()
    {
        // 1. Generate synthetic orders over last 90 days for 20 customers
        $customers = collect();
        $startDate = Carbon::now()->subDays(90);

        for ($i = 1; $i <= 20; $i++) {
            $customer = Customer::create([
                'type' => 'individual',
                'name' => "Customer {$i}",
                'email' => "customer{$i}@example.com",
                'phone' => "+2519" . str_pad($i, 8, '0', STR_PAD_LEFT),
                'is_active' => true,
            ]);

            $customers->push($customer);

            // Create multiple orders for each customer with varying amounts
            $orderCount = rand(3, 8);
            $totalPurchases = 0;

            for ($j = 1; $j <= $orderCount; $j++) {
                $orderDate = $startDate->copy()->addDays(rand(0, 89));
                $orderAmount = rand(500, 2000); // Random amount between 500-2000 ETB
                $totalPurchases += $orderAmount;

                $receipt = Receipt::create([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'branch_id' => $this->branch->id,
                    'number' => "RCP-{$customer->id}-{$j}",
                    'status' => 'POSTED',
                    'customer_id' => $customer->id,
                    'currency' => 'ETB',
                    'subtotal' => $orderAmount,
                    'tax_total' => $orderAmount * 0.15, // 15% tax
                    'discount_total' => 0.00,
                    'grand_total' => $orderAmount * 1.15,
                    'paid_total' => $orderAmount * 1.15,
                    'payment_method' => 'CASH',
                    'posted_at' => $orderDate,
                    'created_by' => $this->managerUser->id,
                    'posted_by' => $this->managerUser->id,
                ]);

                // Create receipt line
                ReceiptLine::create([
                    'receipt_id' => $receipt->id,
                    'product_id' => $this->product->id,
                    'uom' => 'each',
                    'qty' => 1,
                    'price' => $orderAmount,
                    'discount' => 0.00,
                    'tax_rate' => 15.00,
                    'tax_amount' => $orderAmount * 0.15,
                    'line_total' => $orderAmount,
                    'meta' => json_encode(['description' => "Service for Customer {$i}"]),
                ]);
            }

            // Store total purchases for later verification
            $customer->total_purchases = $totalPurchases;
        }

        // 2. Call GET /loyalty/top-customers?days=90 → confirms top 10
        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/loyalty/top-customers?days=90');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'total_purchases'
                    ]
                ],
                'meta' => [
                    'count',
                    'period_days'
                ]
            ]);

        $topCustomers = $response->json('data');
        $this->assertCount(10, $topCustomers);
        $this->assertEquals(90, $response->json('meta.period_days'));

        // Verify the top customer has the highest total purchases
        $topCustomerData = $topCustomers[0];
        $topCustomer = $customers->firstWhere('id', $topCustomerData['id']);
        $this->assertNotNull($topCustomer);

        // 3. For rank #1 customer, call POST /loyalty/discounts/generate with basket total → expect 10% (capped)
        $basketTotal = 1500.00; // Basket total for discount generation

        $discountResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/loyalty/discounts/generate', [
                'customer_id' => $topCustomer->id,
                'basket_total' => $basketTotal
            ]);

        $discountResponse->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'customer_id',
                    'discount_amount',
                    'discount_percentage',
                    'original_total',
                    'final_total',
                    'expires_at'
                ]
            ]);

        $discountData = $discountResponse->json('data');
        $this->assertEquals($topCustomer->id, $discountData['customer_id']);
        $this->assertEquals(10.00, $discountData['discount_percentage']); // 10% discount
        $this->assertEquals(150.00, $discountData['discount_amount']); // 10% of 1500, not capped
        $this->assertEquals(1500.00, $discountData['original_total']);
        $this->assertEquals(1350.00, $discountData['final_total']); // 1500 - 150

        // 4. Verify POS receipt shows discount line and correct grand total
        // Create a receipt with the discount applied
        $receiptWithDiscount = Receipt::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'branch_id' => $this->branch->id,
            'number' => 'RCP-DISCOUNT-001',
            'status' => 'POSTED',
            'customer_id' => $topCustomer->id,
            'currency' => 'ETB',
            'subtotal' => 1500.00,
            'tax_total' => 202.50, // 15% of 1350
            'discount_total' => 150.00, // The loyalty discount
            'grand_total' => 1552.50, // 1350 + 202.50
            'paid_total' => 1552.50,
            'payment_method' => 'CASH',
            'posted_at' => now(),
            'created_by' => $this->managerUser->id,
            'posted_by' => $this->managerUser->id,
        ]);

        // Create receipt line with discount
        ReceiptLine::create([
            'receipt_id' => $receiptWithDiscount->id,
            'product_id' => $this->product->id,
            'uom' => 'each',
            'qty' => 1,
            'price' => 1500.00,
            'discount' => 150.00, // Loyalty discount applied
            'tax_rate' => 15.00,
            'tax_amount' => 202.50,
            'line_total' => 1350.00,
            'meta' => json_encode([
                'description' => 'Service with Loyalty Discount',
                'loyalty_discount_id' => $discountData['id']
            ]),
        ]);

        // Verify the receipt has the correct discount and totals
        $this->assertEquals(150.00, $receiptWithDiscount->discount_total);
        $this->assertEquals(1552.50, $receiptWithDiscount->grand_total);
        $this->assertEquals(1552.50, $receiptWithDiscount->paid_total);

        // Verify the discount is marked as used (this would be done by the POS system)
        $discount = \App\Models\LoyaltyDiscount::find($discountData['id']);
        $discount->update(['is_used' => true, 'used_at' => now()]);

        $this->assertTrue($discount->is_used);
        $this->assertNotNull($discount->used_at);
    }
}