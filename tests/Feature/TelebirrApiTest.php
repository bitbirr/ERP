<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\TelebirrAgent;
use App\Models\TelebirrTransaction;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class TelebirrApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Assign telebirr.view capability to the default test user
        UserPolicy::create([
            'user_id' => $this->user->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.view',
            'granted' => true,
        ]);

        // Assign telebirr.void capability for void operations
        UserPolicy::create([
            'user_id' => $this->user->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.void',
            'granted' => true,
        ]);
    }

    /** @test */
    public function it_can_list_agents()
    {
        TelebirrAgent::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/telebirr/agents');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'short_code',
                        'phone',
                        'location',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ],
            ]);
    }

    /** @test */
    public function it_can_show_single_agent()
    {
        $agent = TelebirrAgent::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/telebirr/agents/{$agent->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'short_code' => $agent->short_code,
                    'status' => $agent->status,
                ],
            ]);
    }

    /** @test */
    public function it_can_create_agent()
    {
        $agentData = [
            'name' => 'Test Agent Corp',
            'short_code' => 'TST001',
            'phone' => '+251911123456',
            'location' => 'Addis Ababa',
            'status' => 'Active',
            'notes' => 'Test agent notes',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/agents', $agentData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Agent created successfully',
                'data' => [
                    'name' => 'Test Agent Corp',
                    'short_code' => 'TST001',
                    'status' => 'Active',
                ],
            ]);

        $this->assertDatabaseHas('telebirr_agents', [
            'name' => 'Test Agent Corp',
            'short_code' => 'TST001',
        ]);
    }

    /** @test */
    public function it_can_update_agent()
    {
        $agent = TelebirrAgent::factory()->create();
        $updateData = [
            'name' => 'Updated Agent Name',
            'phone' => '+251922654321',
        ];

        $response = $this->actingAs($this->user)
            ->patchJson("/api/telebirr/agents/{$agent->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Agent updated successfully',
                'data' => [
                    'id' => $agent->id,
                    'name' => 'Updated Agent Name',
                    'phone' => '+251922654321',
                ],
            ]);

        $this->assertDatabaseHas('telebirr_agents', [
            'id' => $agent->id,
            'name' => 'Updated Agent Name',
            'phone' => '+251922654321',
        ]);
    }

    /** @test */
    public function it_can_list_transactions()
    {
        TelebirrTransaction::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/telebirr/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'tx_type',
                        'amount',
                        'currency',
                        'status',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ],
            ]);
    }

    /** @test */
    public function it_can_show_single_transaction()
    {
        $transaction = TelebirrTransaction::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/telebirr/transactions/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $transaction->id,
                    'tx_type' => $transaction->tx_type,
                    'amount' => $transaction->amount,
                    'status' => $transaction->status,
                ],
            ]);
    }

    /** @test */
    public function it_validates_agent_creation_request()
    {
        $invalidData = [
            'name' => '', // Required field empty
            'short_code' => 'INVALID CODE WITH SPACES', // Invalid format
            'status' => 'Unknown', // Invalid status
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/agents', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'name',
                    'short_code',
                    'status',
                ],
            ]);
    }

    /** @test */
    public function it_can_filter_agents_by_status()
    {
        TelebirrAgent::factory()->count(2)->active()->create();
        TelebirrAgent::factory()->count(1)->inactive()->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/telebirr/agents?status=Active');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        $response = $this->actingAs($this->user)
            ->getJson('/api/telebirr/agents?status=Inactive');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function it_can_search_agents()
    {
        TelebirrAgent::factory()->create(['name' => 'Alpha Corp']);
        TelebirrAgent::factory()->create(['name' => 'Beta Corp']);
        TelebirrAgent::factory()->create(['short_code' => 'ACP001']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/telebirr/agents?search=Alpha');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Alpha Corp', $response->json('data.0.name'));
    }

    /** @test */
    public function it_can_paginate_agents()
    {
        TelebirrAgent::factory()->count(15)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/telebirr/agents?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
        $this->assertEquals(5, $response->json('meta.per_page'));
        $this->assertEquals(3, $response->json('meta.last_page'));
    }

    /** @test */
    public function it_returns_404_for_nonexistent_agent()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/telebirr/agents/99999');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_can_get_reconciliation_data()
    {
        $fromDate = '2024-01-01';
        $toDate = '2024-01-31';

        TelebirrTransaction::factory()->count(5)->create([
            'created_at' => now()->between($fromDate, $toDate),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/telebirr/reconciliation?date_from={$fromDate}&date_to={$toDate}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period' => [
                    'from',
                    'to',
                ],
                'matched' => [
                    'status',
                    'message',
                ],
                'summary' => [
                    'transactions' => [
                        'total_count',
                        'total_amount',
                        'by_type',
                    ],
                    'gl_journals' => [
                        'total_journals',
                        'total_debit',
                        'total_credit',
                    ],
                ],
                'variances' => [
                    'transaction_vs_gl_count',
                    'transaction_vs_gl_amount',
                    'gl_debit_vs_credit',
                ],
                'issues' => [
                    'unmatched_transactions',
                    'unmatched_journals',
                    'unmatched_transaction_details',
                    'unmatched_journal_details',
                ],
                'generated_at',
            ]);
    }

    /** @test */
    public function it_can_get_agent_balances_report()
    {
        TelebirrAgent::factory()->count(3)->active()->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/telebirr/reports/agent-balances');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'agent' => [
                            'id',
                            'name',
                            'short_code',
                        ],
                        'outstanding_balance',
                        'last_transaction',
                    ],
                ],
                'generated_at',
            ]);
    }

    /** @test */
    public function it_can_get_transaction_summary_report()
    {
        $fromDate = '2024-01-01';
        $toDate = '2024-01-31';

        TelebirrTransaction::factory()->count(10)->create([
            'created_at' => $this->faker->dateTimeBetween($fromDate, $toDate),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/telebirr/reports/transaction-summary?date_from={$fromDate}&date_to={$toDate}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period' => [
                    'from',
                    'to',
                ],
                'totals' => [
                    'count',
                    'amount',
                ],
                'by_type',
                'by_agent',
            ]);
    }

    /** @test */
    public function it_validates_reconciliation_date_parameters()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/telebirr/reconciliation?date_from=2024-01-01');

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'date_to',
                ],
            ]);
    }

    /** @test */
    public function it_validates_transaction_summary_date_parameters()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/telebirr/reports/transaction-summary?date_from=2024-01-01');

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'date_to',
                ],
            ]);
    }
}