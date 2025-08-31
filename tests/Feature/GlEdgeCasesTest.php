<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GlJournal;
use App\Models\GlAccount;
use App\Models\GlLine;
use App\Models\Branch;
use App\Models\User;
use App\Models\UserPolicy;
use App\Models\IdempotencyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GlEdgeCasesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Branch $branch;
    protected GlAccount $debitAccount;
    protected GlAccount $creditAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->branch = Branch::factory()->create();

        // Create test accounts
        $this->debitAccount = GlAccount::factory()->create([
            'is_postable' => true,
            'status' => 'ACTIVE'
        ]);
        $this->creditAccount = GlAccount::factory()->create([
            'is_postable' => true,
            'status' => 'ACTIVE'
        ]);

        // Assign GL capabilities
        UserPolicy::create([
            'user_id' => $this->user->id,
            'branch_id' => null,
            'capability_key' => 'gl.view',
            'granted' => true,
        ]);

        UserPolicy::create([
            'user_id' => $this->user->id,
            'branch_id' => null,
            'capability_key' => 'gl.create',
            'granted' => true,
        ]);

        UserPolicy::create([
            'user_id' => $this->user->id,
            'branch_id' => null,
            'capability_key' => 'gl.post',
            'granted' => true,
        ]);
    }

    /** @test */
    public function it_handles_idempotent_gl_journal_posting()
    {
        $journal = GlJournal::factory()->create(['status' => 'DRAFT']);
        $idempotencyKey = $this->faker->uuid();

        // First request should succeed
        $response1 = $this->postJson("/api/gl/journals/{$journal->id}/post", [
            'idempotency_key' => $idempotencyKey,
        ]);

        $response1->assertStatus(200)
            ->assertJson(['message' => 'Journal posted successfully']);

        // Second request with same key should return same result
        $response2 = $this->postJson("/api/gl/journals/{$journal->id}/post", [
            'idempotency_key' => $idempotencyKey,
        ]);

        $response2->assertStatus(200)
            ->assertJson(['message' => 'Journal posted successfully']);

        // Verify journal is still posted
        $this->assertDatabaseHas('gl_journals', [
            'id' => $journal->id,
            'status' => 'POSTED',
        ]);

        // Verify only one idempotency key record exists
        $this->assertEquals(1, IdempotencyKey::where('key', $idempotencyKey)->count());
    }

    /** @test */
    public function it_rejects_idempotent_request_with_different_payload()
    {
        $journal = GlJournal::factory()->create(['status' => 'DRAFT']);
        $idempotencyKey = $this->faker->uuid();

        // First request
        $this->actingAs($this->user)
            ->postJson("/api/gl/journals/{$journal->id}/post", [
                'idempotency_key' => $idempotencyKey,
                'extra_param' => 'value1',
            ]);

        // Second request with same key but different payload should fail
        $response = $this->actingAs($this->user)
            ->postJson("/api/gl/journals/{$journal->id}/post", [
                'idempotency_key' => $idempotencyKey,
                'extra_param' => 'value2', // Different payload
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_handles_large_amounts_with_precision()
    {
        $largeAmount = 999999999.99; // Maximum allowed amount

        $journalData = [
            'journal_date' => now()->format('Y-m-d'),
            'currency' => 'ETB',
            'reference' => 'LARGE-AMOUNT-TEST',
            'memo' => 'Large amount precision test',
            'lines' => [
                [
                    'account_id' => $this->debitAccount->id,
                    'debit' => $largeAmount,
                    'credit' => 0.00,
                    'memo' => 'Large debit',
                ],
                [
                    'account_id' => $this->creditAccount->id,
                    'debit' => 0.00,
                    'credit' => $largeAmount,
                    'memo' => 'Large credit',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $journalData);

        $response->assertStatus(201);

        // Verify amounts are stored correctly
        $this->assertDatabaseHas('gl_lines', [
            'debit' => $largeAmount,
        ]);

        $this->assertDatabaseHas('gl_lines', [
            'credit' => $largeAmount,
        ]);
    }

    /** @test */
    public function it_rejects_amounts_exceeding_maximum()
    {
        $excessiveAmount = 1000000000.00; // Exceeds maximum

        $journalData = [
            'journal_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'account_id' => $this->debitAccount->id,
                    'debit' => $excessiveAmount,
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $this->creditAccount->id,
                    'debit' => 0.00,
                    'credit' => $excessiveAmount,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $journalData);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /** @test */
    public function it_handles_rounding_with_floating_point_precision()
    {
        // Test with amounts that might cause floating point issues
        $amount1 = 100.01;
        $amount2 = 99.99;
        $expectedTotal = 200.00;

        $journalData = [
            'journal_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'account_id' => $this->debitAccount->id,
                    'debit' => $amount1,
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $this->debitAccount->id,
                    'debit' => $amount2,
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $this->creditAccount->id,
                    'debit' => 0.00,
                    'credit' => $expectedTotal,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $journalData);

        $response->assertStatus(201);

        // Verify the journal balances correctly
        $journal = GlJournal::latest()->first();
        $this->assertEquals(0, $journal->getTotalDebit() - $journal->getTotalCredit());
    }

    /** @test */
    public function it_ignores_extra_payload_fields_mass_assignment_protection()
    {
        $journalData = [
            'journal_date' => now()->format('Y-m-d'),
            'reference' => 'TAMPERING-TEST',
            'memo' => 'Payload tampering test',
            // These should be ignored
            'malicious_field' => 'should_be_ignored',
            'status' => 'POSTED', // Should not be able to set status directly
            'posted_at' => now(),
            'lines' => [
                [
                    'account_id' => $this->debitAccount->id,
                    'debit' => 1000.00,
                    'credit' => 0.00,
                    'malicious_line_field' => 'should_be_ignored',
                ],
                [
                    'account_id' => $this->creditAccount->id,
                    'debit' => 0.00,
                    'credit' => 1000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $journalData);

        $response->assertStatus(201);

        $journal = GlJournal::where('reference', 'TAMPERING-TEST')->first();

        // Verify malicious fields were ignored
        $this->assertEquals('DRAFT', $journal->status); // Should still be DRAFT
        $this->assertNull($journal->posted_at); // Should not be set

        // Verify journal was created successfully despite extra fields
        $this->assertDatabaseHas('gl_journals', [
            'reference' => 'TAMPERING-TEST',
            'status' => 'DRAFT',
        ]);
    }

    /** @test */
    public function it_enforces_rate_limiting_on_gl_operations()
    {
        $journal = GlJournal::factory()->create(['status' => 'DRAFT']);

        // Make multiple rapid requests to trigger rate limit
        for ($i = 0; $i < 6; $i++) { // Exceeds the 5 request limit
            $response = $this->actingAs($this->user)
                ->postJson('/api/gl/journals', [
                    'journal_date' => now()->format('Y-m-d'),
                    'lines' => [
                        [
                            'account_id' => $this->debitAccount->id,
                            'debit' => 100.00,
                            'credit' => 0.00,
                        ],
                        [
                            'account_id' => $this->creditAccount->id,
                            'debit' => 0.00,
                            'credit' => 100.00,
                        ],
                    ],
                ]);
        }

        // The last request should be rate limited
        $response->assertStatus(429)
            ->assertJson([
                'message' => 'Too many requests. Please try again later.',
                'error' => 'RATE_LIMIT_EXCEEDED',
            ]);
    }

    /** @test */
    public function it_monitors_query_count_on_gl_operations()
    {
        // Create multiple journals to test query monitoring
        for ($i = 0; $i < 10; $i++) {
            GlJournal::factory()->create(['status' => 'DRAFT']);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/gl/journals');

        $response->assertStatus(200);

        // Check that query count header is present
        $this->assertTrue($response->headers->has('X-Query-Count'));
        $queryCount = (int) $response->headers->get('X-Query-Count');

        // Should have reasonable query count (not excessive)
        $this->assertLessThan(50, $queryCount);
    }

    /** @test */
    public function it_handles_concurrent_idempotent_requests()
    {
        $journal = GlJournal::factory()->create(['status' => 'DRAFT']);
        $idempotencyKey = $this->faker->uuid();

        // Simulate concurrent requests by clearing any existing locks
        Cache::flush();

        // Both requests should succeed but only one should actually post
        $responses = [];

        // First request
        $responses[] = $this->actingAs($this->user)
            ->postJson("/api/gl/journals/{$journal->id}/post", [
                'idempotency_key' => $idempotencyKey,
            ]);

        // Second concurrent request
        $responses[] = $this->actingAs($this->user)
            ->postJson("/api/gl/journals/{$journal->id}/post", [
                'idempotency_key' => $idempotencyKey,
            ]);

        // Both should return success
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Journal should be posted exactly once
        $this->assertDatabaseHas('gl_journals', [
            'id' => $journal->id,
            'status' => 'POSTED',
        ]);

        // Should have only one successful idempotency record
        $successfulKeys = IdempotencyKey::where('key', $idempotencyKey)
            ->where('status', 'SUCCEEDED')
            ->count();
        $this->assertEquals(1, $successfulKeys);
    }

    /** @test */
    public function it_validates_decimal_precision_in_amounts()
    {
        $journalData = [
            'journal_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'account_id' => $this->debitAccount->id,
                    'debit' => 100.123, // More than 2 decimal places
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $this->creditAccount->id,
                    'debit' => 0.00,
                    'credit' => 100.123,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $journalData);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /** @test */
    public function it_handles_zero_amount_lines_correctly()
    {
        $journalData = [
            'journal_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'account_id' => $this->debitAccount->id,
                    'debit' => 0.00,
                    'credit' => 0.00, // Zero amount line
                ],
                [
                    'account_id' => $this->creditAccount->id,
                    'debit' => 0.00,
                    'credit' => 100.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $journalData);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }
}