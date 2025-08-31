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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GlMiddlewareTest extends TestCase
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
    public function query_count_guard_adds_headers_to_response()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/gl/journals');

        $response->assertStatus(200);

        // Check that query count headers are present
        $this->assertTrue($response->headers->has('X-Query-Count'));
        $this->assertFalse($response->headers->has('X-Query-Count-Exceeded'));

        $queryCount = (int) $response->headers->get('X-Query-Count');
        $this->assertGreaterThan(0, $queryCount);
    }

    /** @test */
    public function query_count_guard_detects_excessive_queries()
    {
        // Create many journals to potentially trigger N+1 issues
        for ($i = 0; $i < 100; $i++) {
            GlJournal::factory()->create(['status' => 'DRAFT']);
        }

        // Make a request that might trigger many queries
        $response = $this->actingAs($this->user)
            ->getJson('/api/gl/journals?per_page=100');

        $response->assertStatus(200);

        // Check headers
        $this->assertTrue($response->headers->has('X-Query-Count'));
        $queryCount = (int) $response->headers->get('X-Query-Count');

        // If query count is high, it should be flagged
        if ($queryCount > 30) {
            $this->assertTrue($response->headers->has('X-Query-Count-Exceeded'));
            $this->assertEquals('true', $response->headers->get('X-Query-Count-Exceeded'));
        }
    }

    /** @test */
    public function gl_rate_limit_allows_normal_requests()
    {
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

        $response->assertStatus(201);

        // Check rate limit headers
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('X-RateLimit-Reset'));

        $this->assertEquals('5', $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals('4', $response->headers->get('X-RateLimit-Remaining'));
    }

    /** @test */
    public function gl_rate_limit_blocks_excessive_requests()
    {
        // Make requests up to the limit
        for ($i = 0; $i < 5; $i++) {
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

            if ($i < 4) {
                $response->assertStatus(201);
            }
        }

        // The 6th request should be rate limited
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

        $response->assertStatus(429)
            ->assertJson([
                'message' => 'Too many requests. Please try again later.',
                'error' => 'RATE_LIMIT_EXCEEDED',
            ])
            ->assertHeader('Retry-After');
    }

    /** @test */
    public function gl_rate_limit_resets_after_time_window()
    {
        // Fill up the rate limit
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->user)
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

        // Simulate time passing by clearing the cache
        Cache::flush();

        // Next request should succeed
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

        $response->assertStatus(201);
    }

    /** @test */
    public function query_count_guard_skips_non_api_routes()
    {
        // Test with a non-API route (this would need to be adjusted based on actual routes)
        $response = $this->actingAs($this->user)
            ->get('/ping'); // Assuming this is a non-API route

        $response->assertStatus(200);

        // Should not have query count headers for non-API routes
        $this->assertFalse($response->headers->has('X-Query-Count'));
    }

    /** @test */
    public function gl_rate_limit_different_endpoints_have_separate_limits()
    {
        // Fill up rate limit for journal creation
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->user)
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

        // Journal listing should still work (different rate limit)
        $response = $this->actingAs($this->user)
            ->getJson('/api/gl/journals');

        $response->assertStatus(200);
    }

    /** @test */
    public function middleware_works_with_authenticated_users_only()
    {
        // Test without authentication
        $response = $this->postJson('/api/gl/journals', [
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

        $response->assertStatus(401); // Unauthorized

        // Rate limit headers should not be present for unauthenticated requests
        $this->assertFalse($response->headers->has('X-RateLimit-Limit'));
    }
}