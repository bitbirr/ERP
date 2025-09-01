<?php

namespace Tests\Feature\Telebirr;

use App\Models\TelebirrAgent;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

class ValidationChecklistTest extends BaseTestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $distributorUser;
    protected $financeUser;
    protected $managerUser;
    protected $agent;
    protected $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed necessary data for tests
        $this->seed([
            \Database\Seeders\ChartOfAccountsSeeder::class,
            \Database\Seeders\TelebirrAgentsSeeder::class,
            \Database\Seeders\BankAccountsSeeder::class,
        ]);

        // Create test agent
        $this->agent = TelebirrAgent::factory()->create([
            'status' => 'Active',
            'short_code' => 'VALIDATION_AGENT'
        ]);

        // Create test GL account first
        $glAccount = \App\Models\GlAccount::factory()->create();

        // Create test bank account
        $this->bankAccount = BankAccount::create([
            'name' => 'Validation Test Bank Account',
            'external_number' => 'VALIDATION_BANK',
            'account_number' => '1234567890',
            'is_active' => true,
            'gl_account_id' => $glAccount->id,
        ]);

        // Create Admin user (all capabilities)
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
        UserPolicy::create(['user_id' => $this->adminUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.view', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->adminUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.post', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->adminUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.void', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->adminUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.manage', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->adminUser->id, 'branch_id' => null, 'capability_key' => 'gl.view', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->adminUser->id, 'branch_id' => null, 'capability_key' => 'gl.post', 'granted' => true]);

        // Create Manager user (read-only)
        $this->managerUser = User::factory()->create(['email' => 'manager@test.com']);
        UserPolicy::create(['user_id' => $this->managerUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.view', 'granted' => true]);

        // Create Distributor user (telebirr operations)
        $this->distributorUser = User::factory()->create(['email' => 'distributor@test.com']);
        UserPolicy::create(['user_id' => $this->distributorUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.view', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->distributorUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.post', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->distributorUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.void', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->distributorUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.manage', 'granted' => true]);

        // Create Finance user (telebirr + finance)
        $this->financeUser = User::factory()->create(['email' => 'finance@test.com']);
        UserPolicy::create(['user_id' => $this->financeUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.view', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->financeUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.post', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->financeUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.void', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->financeUser->id, 'branch_id' => null, 'capability_key' => 'telebirr.manage', 'granted' => true]);
        UserPolicy::create(['user_id' => $this->financeUser->id, 'branch_id' => null, 'capability_key' => 'gl.view', 'granted' => true]);
    }

    /** @test */
    public function validation_checklist_http_layer_status_codes_and_payloads()
    {
        // Test successful TOPUP transaction
        $topupData = [
            'amount' => 1000.00,
            'currency' => 'ETB',
            'idempotency_key' => 'validation-topup-' . now()->timestamp,
            'external_ref' => 'VALIDATION_TOPUP',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'BANK_TRANSFER',
            'remarks' => 'Validation Checklist Topup'
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', $topupData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'tx_type',
                    'amount',
                    'currency',
                    'status',
                    'idempotency_key',
                    'external_ref',
                    'created_at'
                ]
            ]);

        // Test successful ISSUE transaction
        $issueData = [
            'amount' => 500.00,
            'currency' => 'ETB',
            'idempotency_key' => 'validation-issue-' . now()->timestamp,
            'external_ref' => 'VALIDATION_ISSUE',
            'agent_short_code' => $this->agent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'Validation Checklist Issue'
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/issue', $issueData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'tx_type',
                    'amount',
                    'currency',
                    'status',
                    'idempotency_key',
                    'external_ref',
                    'agent_id',
                    'created_at'
                ]
            ]);

        // Test validation error (missing required fields)
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'amount',
                    'idempotency_key',
                    'bank_external_number',
                    'payment_method'
                ]
            ]);
    }

    /** @test */
    public function validation_checklist_idempotency_prevents_double_booking()
    {
        $topupData = [
            'amount' => 750.00,
            'currency' => 'ETB',
            'idempotency_key' => 'validation-idempotent-' . now()->timestamp,
            'external_ref' => 'VALIDATION_IDEMPOTENT',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'BANK_TRANSFER',
            'remarks' => 'Validation Checklist Idempotency'
        ];

        // First request - should succeed
        $firstResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', $topupData);

        $firstResponse->assertStatus(201);

        // Second request with same idempotency key - should return validation error
        $secondResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', $topupData);

        // The idempotency validation happens at the request level and returns 422
        $secondResponse->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);

        // Verify only one transaction was created
        $transactions = \App\Models\TelebirrTransaction::where('idempotency_key', $topupData['idempotency_key'])->get();
        $this->assertCount(1, $transactions);
        $this->assertEquals('Posted', $transactions->first()->status);
    }

    /** @test */
    public function validation_checklist_balances_update_correctly_and_void_removes_impact()
    {
        // Initial balance check
        $initialBalanceResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/telebirr/reports/agent-balances');

        $initialBalanceResponse->assertStatus(200);
        $initialBalances = $initialBalanceResponse->json('data');
        $initialAgentBalance = collect($initialBalances)->firstWhere('agent.short_code', $this->agent->short_code);
        $initialOutstanding = $initialAgentBalance ? $initialAgentBalance['outstanding_balance'] : 0;

        // Post ISSUE transaction (increases agent balance)
        $issueData = [
            'amount' => 1000.00,
            'currency' => 'ETB',
            'idempotency_key' => 'validation-balance-issue-' . now()->timestamp,
            'external_ref' => 'VALIDATION_BALANCE_ISSUE',
            'agent_short_code' => $this->agent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'Validation Checklist Balance Issue'
        ];

        $issueResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/issue', $issueData);

        $issueResponse->assertStatus(201);
        $issueTransaction = \App\Models\TelebirrTransaction::where('idempotency_key', $issueData['idempotency_key'])->first();

        // Check balance after ISSUE
        $afterIssueBalanceResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/telebirr/reports/agent-balances');

        $afterIssueBalanceResponse->assertStatus(200);
        $afterIssueBalances = $afterIssueBalanceResponse->json('data');
        $afterIssueAgentBalance = collect($afterIssueBalances)->firstWhere('agent.short_code', $this->agent->short_code);
        $this->assertEquals($initialOutstanding + 1000.00, $afterIssueAgentBalance['outstanding_balance']);

        // Void the transaction
        $voidResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson('/api/telebirr/transactions/' . $issueTransaction->id . '/void');

        $voidResponse->assertStatus(200);

        // Check balance after VOID - should be back to initial
        $afterVoidBalanceResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/telebirr/reports/agent-balances');

        $afterVoidBalanceResponse->assertStatus(200);
        $afterVoidBalances = $afterVoidBalanceResponse->json('data');
        $afterVoidAgentBalance = collect($afterVoidBalances)->firstWhere('agent.short_code', $this->agent->short_code);
        $this->assertEquals($initialOutstanding, $afterVoidAgentBalance['outstanding_balance']);
    }

    /** @test */
    public function validation_checklist_reports_reflect_operations_for_period()
    {
        $startDate = now()->subDays(1)->toDateString();
        $endDate = now()->addDays(1)->toDateString();

        // Create multiple transactions
        $transactions = [
            [
                'type' => 'topup',
                'amount' => 2000.00,
                'idempotency_key' => 'validation-report-topup-' . now()->timestamp,
                'external_ref' => 'VALIDATION_REPORT_TOPUP'
            ],
            [
                'type' => 'issue',
                'amount' => 800.00,
                'idempotency_key' => 'validation-report-issue-' . now()->timestamp,
                'external_ref' => 'VALIDATION_REPORT_ISSUE'
            ],
            [
                'type' => 'repay',
                'amount' => 300.00,
                'idempotency_key' => 'validation-report-repay-' . now()->timestamp,
                'external_ref' => 'VALIDATION_REPORT_REPAY'
            ]
        ];

        foreach ($transactions as $tx) {
            if ($tx['type'] === 'topup') {
                $data = [
                    'amount' => $tx['amount'],
                    'currency' => 'ETB',
                    'idempotency_key' => $tx['idempotency_key'],
                    'external_ref' => $tx['external_ref'],
                    'bank_external_number' => $this->bankAccount->external_number,
                    'payment_method' => 'BANK_TRANSFER'
                ];
                $response = $this->actingAs($this->adminUser, 'sanctum')
                    ->postJson('/api/telebirr/transactions/topup', $data);
                $response->assertStatus(201);
            } elseif ($tx['type'] === 'issue') {
                $data = [
                    'amount' => $tx['amount'],
                    'currency' => 'ETB',
                    'idempotency_key' => $tx['idempotency_key'],
                    'external_ref' => $tx['external_ref'],
                    'agent_short_code' => $this->agent->short_code,
                    'payment_method' => 'CASH',
                    'remarks' => 'Validation report issue transaction'
                ];
                $response = $this->actingAs($this->adminUser, 'sanctum')
                    ->postJson('/api/telebirr/transactions/issue', $data);
                $response->assertStatus(201);
            } elseif ($tx['type'] === 'repay') {
                $data = [
                    'amount' => $tx['amount'],
                    'currency' => 'ETB',
                    'idempotency_key' => $tx['idempotency_key'],
                    'external_ref' => $tx['external_ref'],
                    'agent_short_code' => $this->agent->short_code,
                    'bank_external_number' => $this->bankAccount->external_number,
                    'payment_method' => 'BANK_TRANSFER'
                ];
                $response = $this->actingAs($this->adminUser, 'sanctum')
                    ->postJson('/api/telebirr/transactions/repay', $data);
                $response->assertStatus(201);
            }
        }

        // Test Agent Balances Report
        $balancesResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/telebirr/reports/agent-balances');

        $balancesResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'agent' => [
                            'id',
                            'name',
                            'short_code'
                        ],
                        'outstanding_balance',
                        'last_transaction'
                    ]
                ]
            ]);

        // Test Transaction Summary Report
        $summaryResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/telebirr/reports/transaction-summary?date_from=' . $startDate . '&date_to=' . $endDate);

        $summaryResponse->assertStatus(200)
            ->assertJsonStructure([
                'period' => [
                    'from',
                    'to'
                ],
                'totals' => [
                    'count',
                    'amount'
                ],
                'by_type' => [
                    '*' => [
                        'tx_type',
                        'count',
                        'amount'
                    ]
                ],
                'by_agent' => [
                    '*' => [
                        'agent' => [
                            'id',
                            'name',
                            'short_code'
                        ],
                        'count',
                        'amount'
                    ]
                ]
            ]);

        $summary = $summaryResponse->json();
        $this->assertEquals(3, $summary['totals']['count']); // 3 transactions
        $this->assertEquals(3100.00, $summary['totals']['amount']); // 2000 + 800 + 300
    }

    /** @test */
    public function validation_checklist_reconciliation_matches_operational_totals()
    {
        $startDate = now()->subDays(1)->toDateString();
        $endDate = now()->addDays(1)->toDateString();

        // Create transactions for reconciliation
        $reconciliationTransactions = [
            ['type' => 'topup', 'amount' => 5000.00, 'key' => 'recon-topup-1'],
            ['type' => 'issue', 'amount' => 2000.00, 'key' => 'recon-issue-1'],
            ['type' => 'repay', 'amount' => 1000.00, 'key' => 'recon-repay-1'],
            ['type' => 'topup', 'amount' => 3000.00, 'key' => 'recon-topup-2'],
            ['type' => 'issue', 'amount' => 1500.00, 'key' => 'recon-issue-2']
        ];

        $expectedTotalAmount = 0;
        $expectedTotalCount = count($reconciliationTransactions);

        foreach ($reconciliationTransactions as $tx) {
            $expectedTotalAmount += $tx['amount'];

            if ($tx['type'] === 'topup') {
                $data = [
                    'amount' => $tx['amount'],
                    'currency' => 'ETB',
                    'idempotency_key' => $tx['key'],
                    'external_ref' => strtoupper($tx['key']),
                    'bank_external_number' => $this->bankAccount->external_number,
                    'payment_method' => 'BANK_TRANSFER'
                ];
                $this->actingAs($this->adminUser, 'sanctum')
                    ->postJson('/api/telebirr/transactions/topup', $data);
            } elseif ($tx['type'] === 'issue') {
                $data = [
                    'amount' => $tx['amount'],
                    'currency' => 'ETB',
                    'idempotency_key' => $tx['key'],
                    'external_ref' => strtoupper($tx['key']),
                    'agent_short_code' => $this->agent->short_code,
                    'payment_method' => 'CASH',
                    'remarks' => 'Reconciliation test issue transaction'
                ];
                $this->actingAs($this->adminUser, 'sanctum')
                    ->postJson('/api/telebirr/transactions/issue', $data);
            } elseif ($tx['type'] === 'repay') {
                $data = [
                    'amount' => $tx['amount'],
                    'currency' => 'ETB',
                    'idempotency_key' => $tx['key'],
                    'external_ref' => strtoupper($tx['key']),
                    'agent_short_code' => $this->agent->short_code,
                    'bank_external_number' => $this->bankAccount->external_number,
                    'payment_method' => 'BANK_TRANSFER'
                ];
                $this->actingAs($this->adminUser, 'sanctum')
                    ->postJson('/api/telebirr/transactions/repay', $data);
            }
        }

        // Get operational totals from transaction summary
        $summaryResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/telebirr/reports/transaction-summary?date_from=' . $startDate . '&date_to=' . $endDate);

        $summaryResponse->assertStatus(200);
        $summary = $summaryResponse->json();

        // Reconciliation: period data should match operational totals
        $this->assertEquals($expectedTotalCount, $summary['totals']['count']);
        $this->assertEquals($expectedTotalAmount, $summary['totals']['amount']);

        // Verify breakdown by type
        $topupTotal = collect($summary['by_type'])->firstWhere('tx_type', 'TOPUP');
        $issueTotal = collect($summary['by_type'])->firstWhere('tx_type', 'ISSUE');
        $repayTotal = collect($summary['by_type'])->firstWhere('tx_type', 'REPAY');

        $this->assertNotNull($topupTotal);
        $this->assertNotNull($issueTotal);
        $this->assertNotNull($repayTotal);

        $this->assertEquals(2, $topupTotal['count']); // 2 topup transactions
        $this->assertEquals(8000.00, $topupTotal['amount']); // 5000 + 3000

        $this->assertEquals(2, $issueTotal['count']); // 2 issue transactions
        $this->assertEquals(3500.00, $issueTotal['amount']); // 2000 + 1500

        $this->assertEquals(1, $repayTotal['count']); // 1 repay transaction
        $this->assertEquals(1000.00, $repayTotal['amount']); // 1000
    }

    /** @test */
    public function validation_checklist_rbac_actions_align_with_capability_sets()
    {
        // Test Admin user (all capabilities)
        $adminTopupResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', [
                'amount' => 100.00,
                'currency' => 'ETB',
                'idempotency_key' => 'rbac-admin-topup-' . now()->timestamp,
                'external_ref' => 'RBAC_ADMIN_TOPUP',
                'bank_external_number' => $this->bankAccount->external_number,
                'payment_method' => 'BANK_TRANSFER'
            ]);
        $adminTopupResponse->assertStatus(201);

        // Test Distributor user (telebirr operations)
        $distributorTopupResponse = $this->actingAs($this->distributorUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', [
                'amount' => 200.00,
                'currency' => 'ETB',
                'idempotency_key' => 'rbac-distributor-topup-' . now()->timestamp,
                'external_ref' => 'RBAC_DISTRIBUTOR_TOPUP',
                'bank_external_number' => $this->bankAccount->external_number,
                'payment_method' => 'BANK_TRANSFER'
            ]);
        $distributorTopupResponse->assertStatus(201);

        // Test Finance user (telebirr + finance)
        $financeTopupResponse = $this->actingAs($this->financeUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', [
                'amount' => 300.00,
                'currency' => 'ETB',
                'idempotency_key' => 'rbac-finance-topup-' . now()->timestamp,
                'external_ref' => 'RBAC_FINANCE_TOPUP',
                'bank_external_number' => $this->bankAccount->external_number,
                'payment_method' => 'BANK_TRANSFER'
            ]);
        $financeTopupResponse->assertStatus(201);

        // Test Manager user (read-only) - should be denied
        $managerTopupResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', [
                'amount' => 400.00,
                'currency' => 'ETB',
                'idempotency_key' => 'rbac-manager-topup-' . now()->timestamp,
                'external_ref' => 'RBAC_MANAGER_TOPUP',
                'bank_external_number' => $this->bankAccount->external_number,
                'payment_method' => 'BANK_TRANSFER'
            ]);
        $managerTopupResponse->assertStatus(403);

        // Test read permissions for all users
        foreach ([$this->adminUser, $this->distributorUser, $this->financeUser, $this->managerUser] as $user) {
            $readResponse = $this->actingAs($user, 'sanctum')
                ->getJson('/api/telebirr/transactions');
            $readResponse->assertStatus(200);
        }

        // Test Finance user can read GL (additional capability)
        $glResponse = $this->actingAs($this->financeUser, 'sanctum')
            ->getJson('/api/gl/journals');
        $glResponse->assertStatus(200);

        // Test Distributor user cannot read GL (no GL capability)
        $glResponse = $this->actingAs($this->distributorUser, 'sanctum')
            ->getJson('/api/gl/journals');
        $glResponse->assertStatus(403);

        // Test Manager user cannot read GL (no GL capability)
        $glResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/gl/journals');
        $glResponse->assertStatus(403);
    }
}