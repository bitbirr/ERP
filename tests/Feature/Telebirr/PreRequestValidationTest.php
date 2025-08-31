<?php

namespace Tests\Feature\Telebirr;

use App\Models\TelebirrAgent;
use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $agent;
    protected $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create test agent
        $this->agent = TelebirrAgent::factory()->create([
            'status' => 'Active'
        ]);

        // Create test bank account
        $this->bankAccount = BankAccount::factory()->create([
            'is_active' => true
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_validates_missing_token_returns_401()
    {
        // Remove authentication
        $this->withoutMiddleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

        $response = $this->postJson('/api/telebirr/transactions/topup', [
            'amount' => 100.00,
            'idempotency_key' => 'test-key-123'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_required_fields_for_topup()
    {
        $response = $this->postJson('/api/telebirr/transactions/topup', [
            // Missing required fields
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount', 'idempotency_key', 'bank_external_number', 'payment_method']);
    }

    /** @test */
    public function it_validates_amount_greater_than_zero()
    {
        $response = $this->postJson('/api/telebirr/transactions/topup', [
            'amount' => 0,
            'idempotency_key' => 'test-key-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_validates_agent_status_for_issue()
    {
        // Create inactive agent
        $inactiveAgent = TelebirrAgent::factory()->create([
            'status' => 'Inactive'
        ]);

        $response = $this->postJson('/api/telebirr/transactions/issue', [
            'amount' => 100.00,
            'idempotency_key' => 'test-key-123',
            'agent_short_code' => $inactiveAgent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'Test issue'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['agent_short_code']);
    }

    /** @test */
    public function it_validates_idempotency_key_uniqueness()
    {
        // First request
        $this->postJson('/api/telebirr/transactions/topup', [
            'amount' => 100.00,
            'idempotency_key' => 'duplicate-key-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ]);

        // Second request with same key should return existing transaction
        $response = $this->postJson('/api/telebirr/transactions/topup', [
            'amount' => 100.00,
            'idempotency_key' => 'duplicate-key-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'idempotent' => true,
            'message' => 'Topup transaction already processed'
        ]);
    }

    /** @test */
    public function it_validates_currency_format()
    {
        $response = $this->postJson('/api/telebirr/transactions/topup', [
            'amount' => 100.00,
            'currency' => 'INVALID',
            'idempotency_key' => 'test-key-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['currency']);
    }

    /** @test */
    public function it_validates_payment_method_for_issue()
    {
        $response = $this->postJson('/api/telebirr/transactions/issue', [
            'amount' => 100.00,
            'idempotency_key' => 'test-key-123',
            'agent_short_code' => $this->agent->short_code,
            'payment_method' => 'INVALID_METHOD',
            'remarks' => 'Test issue'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
    }
}