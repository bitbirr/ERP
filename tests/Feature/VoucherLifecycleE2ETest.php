<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Capability;
use App\Models\UserRoleAssignment;
use App\Models\VoucherBatch;
use App\Models\Voucher;
use App\Models\VoucherReservation;
use App\Models\VoucherIssuance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class VoucherLifecycleE2ETest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user with voucher capabilities
        $role = Role::create([
            'name' => 'Voucher Manager',
            'slug' => 'voucher-manager',
            'description' => 'Can manage vouchers'
        ]);

        $capabilities = [
            'vouchers.view' => 'View Vouchers',
            'vouchers.manage' => 'Manage Vouchers'
        ];

        foreach ($capabilities as $capKey => $capName) {
            $capability = Capability::firstOrCreate([
                'key' => $capKey
            ], [
                'name' => $capName,
                'key' => $capKey,
                'description' => $capName,
                'group' => 'vouchers'
            ]);
            $role->capabilities()->attach($capability->id);
        }

        $this->user = User::factory()->create();
        UserRoleAssignment::create([
            'user_id' => $this->user->id,
            'role_id' => $role->id
        ]);
    }

    /** @test */
    public function complete_voucher_lifecycle_e2e()
    {
        // Step 1: Receive voucher batch
        $batchData = [
            'batch_number' => 'VOUCHER_BATCH_001',
            'serial_start' => '000100',
            'serial_end' => '000105', // 6 vouchers total
            'total_vouchers' => 6,
            'metadata' => [
                'supplier' => 'Test Supplier',
                'batch_date' => '2025-01-01',
                'voucher_type' => 'gift_card'
            ]
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/vouchers/batches', $batchData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'batch' => [
                        'batch_number' => 'VOUCHER_BATCH_001',
                        'total_vouchers' => 6,
                        'status' => 'processed'
                    ]
                ]
            ]);

        // Verify batch was created with correct data
        $this->assertDatabaseHas('voucher_batches', [
            'batch_number' => 'VOUCHER_BATCH_001',
            'total_vouchers' => 6,
            'serial_start' => '000100',
            'serial_end' => '000105',
            'status' => 'processed'
        ]);

        // Verify vouchers were created
        $batch = VoucherBatch::where('batch_number', 'VOUCHER_BATCH_001')->first();
        $this->assertEquals(6, $batch->vouchers()->count());

        // Verify serial numbers
        $expectedSerials = ['000100', '000101', '000102', '000103', '000104', '000105'];
        foreach ($expectedSerials as $serial) {
            $this->assertDatabaseHas('vouchers', [
                'batch_id' => $batch->id,
                'serial_number' => $serial,
                'status' => 'available'
            ]);
        }

        // Verify serial range in metadata
        $batch->refresh();
        $this->assertEquals('000100', $batch->metadata['serial_range']['start']);
        $this->assertEquals('000105', $batch->metadata['serial_range']['end']);
        $this->assertEquals(6, $batch->metadata['serial_range']['total']);

        // Step 2: Reserve vouchers for an order
        $orderId = 'ORDER_12345';
        $reserveData = [
            'order_id' => $orderId,
            'quantity' => 3,
            'batch_number' => 'VOUCHER_BATCH_001'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/vouchers/reserve', $reserveData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 3
                ]
            ]);

        // Verify reservations were created
        $reservations = VoucherReservation::where('order_id', $orderId)->get();
        $this->assertCount(3, $reservations);

        // Verify voucher statuses were updated
        $reservedVouchers = Voucher::where('status', 'reserved')
            ->where('reserved_for_order_id', $orderId)
            ->get();
        $this->assertCount(3, $reservedVouchers);

        // Step 3: Issue vouchers on fulfillment
        $fulfillmentId = 'FULFILLMENT_001';
        $voucherIds = $reservedVouchers->pluck('id')->toArray();

        $issueData = [
            'order_id' => $orderId,
            'fulfillment_id' => $fulfillmentId,
            'voucher_ids' => $voucherIds
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/vouchers/issue', $issueData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 3
                ]
            ]);

        // Verify issuances were created
        $issuances = VoucherIssuance::where('order_id', $orderId)
            ->where('fulfillment_id', $fulfillmentId)
            ->get();
        $this->assertCount(3, $issuances);

        // Verify voucher statuses were updated to issued
        $issuedVouchers = Voucher::where('status', 'issued')
            ->whereIn('id', $voucherIds)
            ->get();
        $this->assertCount(3, $issuedVouchers);

        // Verify issued_at timestamps
        foreach ($issuedVouchers as $voucher) {
            $this->assertNotNull($voucher->issued_at);
        }

        // Step 4: Verify serial range confirmation in metadata
        foreach ($issuances as $issuance) {
            $this->assertEquals('VOUCHER_BATCH_001', $issuance->metadata['batch_number']);
            $this->assertEquals('fulfillment', $issuance->metadata['issued_via']);
        }

        // Verify remaining vouchers are still available
        $remainingVouchers = Voucher::where('batch_id', $batch->id)
            ->where('status', 'available')
            ->get();
        $this->assertCount(3, $remainingVouchers);
    }

    /** @test */
    public function cannot_receive_duplicate_batch_number()
    {
        $batchData = [
            'batch_number' => 'DUPLICATE_BATCH',
            'serial_start' => '000200',
            'serial_end' => '000202',
            'total_vouchers' => 3,
            'metadata' => []
        ];

        // First batch
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/vouchers/batches', $batchData)
            ->assertStatus(201);

        // Duplicate batch should fail
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/vouchers/batches', $batchData);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to receive voucher batch'
            ]);
    }

    /** @test */
    public function cannot_reserve_more_vouchers_than_available()
    {
        // Create a small batch
        $batchData = [
            'batch_number' => 'SMALL_BATCH',
            'serial_start' => '000300',
            'serial_end' => '000301',
            'total_vouchers' => 2,
            'metadata' => []
        ];

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/vouchers/batches', $batchData);

        // Try to reserve more than available
        $reserveData = [
            'order_id' => 'ORDER_BIG',
            'quantity' => 5, // More than the 2 available
            'batch_number' => 'SMALL_BATCH'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/vouchers/reserve', $reserveData);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to reserve vouchers'
            ]);
    }

    /** @test */
    public function validation_fails_for_invalid_voucher_data()
    {
        $invalidBatchData = [
            'batch_number' => '', // Empty
            'serial_start' => 'invalid', // Invalid format
            'serial_end' => '000001',
            'total_vouchers' => 0, // Zero
            'metadata' => 'not_an_array' // Invalid type
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/vouchers/batches', $invalidBatchData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed'
            ]);
    }
}