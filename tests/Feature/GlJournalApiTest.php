<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GlJournal;
use App\Models\GlAccount;
use App\Models\Branch;
use App\Models\User;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class GlJournalApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Assign GL capabilities to the default test user
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

        UserPolicy::create([
            'user_id' => $this->user->id,
            'branch_id' => null,
            'capability_key' => 'gl.reverse',
            'granted' => true,
        ]);
    }

    /** @test */
    public function it_can_create_gl_journal()
    {
        $branch = Branch::factory()->create();
        $account1 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);
        $account2 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);

        $journalData = [
            'journal_date' => now()->format('Y-m-d'),
            'currency' => 'ETB',
            'reference' => 'TEST-REF-001',
            'memo' => 'Test journal entry',
            'lines' => [
                [
                    'account_id' => $account1->id,
                    'debit' => 1000.00,
                    'credit' => 0.00,
                    'memo' => 'Debit entry',
                ],
                [
                    'account_id' => $account2->id,
                    'debit' => 0.00,
                    'credit' => 1000.00,
                    'memo' => 'Credit entry',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $journalData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'journal_no',
                    'journal_date',
                    'status',
                    'lines',
                ],
            ]);

        $this->assertDatabaseHas('gl_journals', [
            'reference' => 'TEST-REF-001',
            'memo' => 'Test journal entry',
        ]);
    }

    /** @test */
    public function it_validates_gl_journal_creation_request()
    {
        $invalidData = [
            'journal_date' => now()->addDays(1)->format('Y-m-d'), // Future date
            'currency' => 'INVALID', // Invalid currency
            'lines' => [
                [
                    'account_id' => 'invalid-uuid',
                    'debit' => 1000.00,
                    'credit' => 500.00, // Both debit and credit
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    /** @test */
    public function it_can_post_gl_journal()
    {
        $journal = GlJournal::factory()->create(['status' => 'DRAFT']);
        $idempotencyKey = $this->faker->uuid();

        $response = $this->actingAs($this->user)
            ->postJson("/api/gl/journals/{$journal->id}/post", [
                'idempotency_key' => $idempotencyKey,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Journal posted successfully',
            ]);

        $this->assertDatabaseHas('gl_journals', [
            'id' => $journal->id,
            'status' => 'POSTED',
        ]);
    }

    /** @test */
    public function it_can_reverse_gl_journal()
    {
        $journal = GlJournal::factory()->create(['status' => 'POSTED']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/gl/journals/{$journal->id}/reverse", [
                'reason' => 'Test reversal reason with sufficient length',
                'reversal_date' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'journal_no',
                ],
            ]);

        $this->assertDatabaseHas('gl_journals', [
            'id' => $journal->id,
            'status' => 'REVERSED',
        ]);
    }

    /** @test */
    public function it_validates_journal_reversal_request()
    {
        $journal = GlJournal::factory()->create(['status' => 'POSTED']);

        $invalidData = [
            'reason' => 'Short', // Too short
            'reversal_date' => now()->subDays(1)->format('Y-m-d'), // Past date
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/gl/journals/{$journal->id}/reverse", $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'reason',
                    'reversal_date',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_unbalanced_journal()
    {
        $account1 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);
        $account2 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);

        $unbalancedData = [
            'journal_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'account_id' => $account1->id,
                    'debit' => 1000.00,
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $account2->id,
                    'debit' => 0.00,
                    'credit' => 500.00, // Unbalanced
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $unbalancedData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'lines',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_journal_with_single_line()
    {
        $account = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);

        $singleLineData = [
            'journal_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'account_id' => $account->id,
                    'debit' => 1000.00,
                    'credit' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $singleLineData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'lines',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_line_with_both_debit_and_credit()
    {
        $account1 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);
        $account2 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);

        $invalidLineData = [
            'journal_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'account_id' => $account1->id,
                    'debit' => 1000.00,
                    'credit' => 500.00, // Both debit and credit
                ],
                [
                    'account_id' => $account2->id,
                    'debit' => 0.00,
                    'credit' => 1000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $invalidLineData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    /** @test */
    public function it_rejects_line_with_no_debit_or_credit()
    {
        $account1 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);
        $account2 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);

        $invalidLineData = [
            'journal_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'account_id' => $account1->id,
                    'debit' => 0.00,
                    'credit' => 0.00, // Neither debit nor credit
                ],
                [
                    'account_id' => $account2->id,
                    'debit' => 0.00,
                    'credit' => 1000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $invalidLineData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    /** @test */
    public function it_rejects_invalid_currency_format()
    {
        $account1 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);
        $account2 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);

        $invalidCurrencyData = [
            'journal_date' => now()->format('Y-m-d'),
            'currency' => 'INVALIDCURRENCY', // Not 3 characters
            'lines' => [
                [
                    'account_id' => $account1->id,
                    'debit' => 1000.00,
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $account2->id,
                    'debit' => 0.00,
                    'credit' => 1000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $invalidCurrencyData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'currency',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_duplicate_journal_number()
    {
        $existingJournal = GlJournal::factory()->create(['journal_no' => 'TEST-001']);
        $account1 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);
        $account2 = GlAccount::factory()->create(['is_postable' => true, 'status' => 'ACTIVE']);

        $duplicateData = [
            'journal_no' => 'TEST-001', // Duplicate
            'journal_date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'account_id' => $account1->id,
                    'debit' => 1000.00,
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $account2->id,
                    'debit' => 0.00,
                    'credit' => 1000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/gl/journals', $duplicateData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'journal_no',
                ],
            ]);
    }
}