<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use App\Models\TelebirrAgent;
use App\Models\TelebirrTransaction;
use App\Models\GlJournal;
use App\Models\GlAccount;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\AuditLog;

class RoleBasedE2EMatrixTest extends BaseTestCase
{
    use RefreshDatabase, WithFaker;

    protected $users = [];
    protected $testData = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users for each role
        $this->createTestUsers();

        // Create test data
        $this->createTestData();
    }

    protected function createTestUsers()
    {
        $roles = ['admin', 'manager', 'telebirr_distributor', 'sales', 'finance', 'inventory', 'audit'];

        foreach ($roles as $roleSlug) {
            $user = User::factory()->create([
                'name' => ucfirst($roleSlug) . ' User',
                'email' => $roleSlug . '@example.com',
            ]);

            $role = Role::where('slug', $roleSlug)->first();
            if ($role) {
                UserRoleAssignment::create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'branch_id' => null,
                ]);
            }

            $this->users[$roleSlug] = $user;
        }
    }

    protected function createTestData()
    {
        // Create telebirr agent
        $this->testData['agent'] = TelebirrAgent::factory()->create();

        // Create telebirr transaction
        $this->testData['transaction'] = TelebirrTransaction::factory()->create([
            'agent_id' => $this->testData['agent']->id,
        ]);

        // Create GL account
        $this->testData['glAccount'] = GlAccount::factory()->create();

        // Create product
        $this->testData['product'] = Product::factory()->create();

        // Create inventory item
        $this->testData['inventoryItem'] = InventoryItem::factory()->create([
            'product_id' => $this->testData['product']->id,
        ]);
    }

    protected function makeRequest($method, $uri, $user, $data = [])
    {
        return $this->actingAs($user)->json($method, $uri, $data);
    }

    // ==================== ADMIN ROLE TESTS ====================

    /** @test */
    public function admin_can_perform_all_telebirr_operations()
    {
        $user = $this->users['admin'];

        // Test agent operations
        $this->makeRequest('GET', '/api/telebirr/agents', $user)->assertStatus(200);
        $this->makeRequest('POST', '/api/telebirr/agents', [
            'name' => 'Test Agent',
            'short_code' => 'TST001',
            'phone' => '+251911123456',
            'location' => 'Addis Ababa',
            'status' => 'Active',
        ], $user)->assertStatus(201);

        // Test transaction operations
        $this->makeRequest('GET', '/api/telebirr/transactions', $user)->assertStatus(200);
        $this->makeRequest('POST', '/api/telebirr/transactions/topup', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 1000,
            'currency' => 'ETB',
        ], $user)->assertStatus(200);

        // Test reports
        $this->makeRequest('GET', '/api/telebirr/reports/agent-balances', $user)->assertStatus(200);
    }

    /** @test */
    public function admin_can_perform_all_gl_operations()
    {
        $user = $this->users['admin'];

        // Test journal operations
        $this->makeRequest('GET', '/api/gl/journals', $user)->assertStatus(200);
        $this->makeRequest('POST', '/api/gl/journals', [
            'description' => 'Test Journal',
            'source' => 'manual',
            'lines' => [
                [
                    'account_id' => $this->testData['glAccount']->id,
                    'debit' => 1000,
                    'credit' => 0,
                    'description' => 'Test debit',
                ],
                [
                    'account_id' => $this->testData['glAccount']->id,
                    'debit' => 0,
                    'credit' => 1000,
                    'description' => 'Test credit',
                ],
            ],
        ], $user)->assertStatus(201);
    }

    // ==================== MANAGER ROLE TESTS ====================

    /** @test */
    public function manager_can_read_all_data_but_cannot_write()
    {
        $user = $this->users['manager'];

        // Can read telebirr data
        $this->makeRequest('GET', '/api/telebirr/agents', $user)->assertStatus(200);
        $this->makeRequest('GET', '/api/telebirr/transactions', $user)->assertStatus(200);
        $this->makeRequest('GET', '/api/telebirr/reports/agent-balances', $user)->assertStatus(200);

        // Cannot create telebirr data
        $this->makeRequest('POST', '/api/telebirr/agents', [
            'name' => 'Test Agent',
            'short_code' => 'TST001',
            'phone' => '+251911123456',
            'location' => 'Addis Ababa',
            'status' => 'Active',
        ], $user)->assertStatus(403);

        $this->makeRequest('POST', '/api/telebirr/transactions/topup', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 1000,
            'currency' => 'ETB',
        ], $user)->assertStatus(403);

        // Can read GL data
        $this->makeRequest('GET', '/api/gl/journals', $user)->assertStatus(200);
        $this->makeRequest('GET', '/api/gl/accounts', $user)->assertStatus(200);

        // Cannot create GL data
        $this->makeRequest('POST', '/api/gl/journals', [
            'description' => 'Test Journal',
            'source' => 'manual',
            'lines' => [],
        ], $user)->assertStatus(403);
    }

    // ==================== TELEBIRR DISTRIBUTOR ROLE TESTS ====================

    /** @test */
    public function telebirr_distributor_can_create_specific_transactions()
    {
        $user = $this->users['telebirr_distributor'];

        // Can read agents and transactions
        $this->makeRequest('GET', '/api/telebirr/agents', $user)->assertStatus(200);
        $this->makeRequest('GET', '/api/telebirr/transactions', $user)->assertStatus(200);

        // Can create ISSUE, LOAN, REPAY, TOPUP transactions
        $this->makeRequest('POST', '/api/telebirr/transactions/topup', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 1000,
            'currency' => 'ETB',
        ], $user)->assertStatus(200);

        $this->makeRequest('POST', '/api/telebirr/transactions/issue', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 500,
            'currency' => 'ETB',
        ], $user)->assertStatus(200);

        $this->makeRequest('POST', '/api/telebirr/transactions/loan', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 2000,
            'currency' => 'ETB',
        ], $user)->assertStatus(200);

        $this->makeRequest('POST', '/api/telebirr/transactions/repay', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 1500,
            'currency' => 'ETB',
        ], $user)->assertStatus(200);

        // Cannot create agents
        $this->makeRequest('POST', '/api/telebirr/agents', [
            'name' => 'Test Agent',
            'short_code' => 'TST001',
            'phone' => '+251911123456',
            'location' => 'Addis Ababa',
            'status' => 'Active',
        ], $user)->assertStatus(403);

        // Cannot access GL operations
        $this->makeRequest('GET', '/api/gl/journals', $user)->assertStatus(403);
        $this->makeRequest('POST', '/api/gl/journals', [], $user)->assertStatus(403);
    }

    // ==================== SALES ROLE TESTS ====================

    /** @test */
    public function sales_can_perform_basic_sales_operations()
    {
        $user = $this->users['sales'];

        // Can read transactions
        $this->makeRequest('GET', '/api/telebirr/transactions', $user)->assertStatus(200);

        // Cannot create telebirr transactions (no distributor access)
        $this->makeRequest('POST', '/api/telebirr/transactions/topup', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 1000,
            'currency' => 'ETB',
        ], $user)->assertStatus(403);

        // Cannot access GL operations
        $this->makeRequest('GET', '/api/gl/journals', $user)->assertStatus(403);
        $this->makeRequest('POST', '/api/gl/journals', [], $user)->assertStatus(403);

        // Cannot manage agents
        $this->makeRequest('GET', '/api/telebirr/agents', $user)->assertStatus(403);
        $this->makeRequest('POST', '/api/telebirr/agents', [], $user)->assertStatus(403);
    }

    // ==================== FINANCE ROLE TESTS ====================

    /** @test */
    public function finance_can_manage_gl_and_financial_operations()
    {
        $user = $this->users['finance'];

        // Can perform all GL operations
        $this->makeRequest('GET', '/api/gl/journals', $user)->assertStatus(200);
        $this->makeRequest('GET', '/api/gl/accounts', $user)->assertStatus(200);

        $this->makeRequest('POST', '/api/gl/journals', [
            'description' => 'Finance Journal',
            'source' => 'manual',
            'lines' => [
                [
                    'account_id' => $this->testData['glAccount']->id,
                    'debit' => 1000,
                    'credit' => 0,
                    'description' => 'Finance debit',
                ],
                [
                    'account_id' => $this->testData['glAccount']->id,
                    'debit' => 0,
                    'credit' => 1000,
                    'description' => 'Finance credit',
                ],
            ],
        ], $user)->assertStatus(201);

        // Can perform specific telebirr operations (topup, repay)
        $this->makeRequest('POST', '/api/telebirr/transactions/topup', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 1000,
            'currency' => 'ETB',
        ], $user)->assertStatus(200);

        $this->makeRequest('POST', '/api/telebirr/transactions/repay', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 1500,
            'currency' => 'ETB',
        ], $user)->assertStatus(200);

        // Cannot perform other telebirr operations
        $this->makeRequest('POST', '/api/telebirr/transactions/issue', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 500,
            'currency' => 'ETB',
        ], $user)->assertStatus(403);

        $this->makeRequest('POST', '/api/telebirr/transactions/loan', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 2000,
            'currency' => 'ETB',
        ], $user)->assertStatus(403);
    }

    // ==================== INVENTORY ROLE TESTS ====================

    /** @test */
    public function inventory_can_manage_inventory_operations()
    {
        $user = $this->users['inventory'];

        // Note: Inventory endpoints are not fully defined in routes yet
        // This is a placeholder for when inventory endpoints are implemented

        // Cannot access GL operations
        $this->makeRequest('GET', '/api/gl/journals', $user)->assertStatus(403);
        $this->makeRequest('POST', '/api/gl/journals', [], $user)->assertStatus(403);

        // Cannot access telebirr operations
        $this->makeRequest('GET', '/api/telebirr/agents', $user)->assertStatus(403);
        $this->makeRequest('POST', '/api/telebirr/transactions/topup', [], $user)->assertStatus(403);
    }

    // ==================== AUDIT ROLE TESTS ====================

    /** @test */
    public function audit_has_read_only_access_to_audit_logs_and_gl()
    {
        $user = $this->users['audit'];

        // Can read GL journals (for audit purposes)
        $this->makeRequest('GET', '/api/gl/journals', $user)->assertStatus(200);
        $this->makeRequest('GET', '/api/gl/accounts', $user)->assertStatus(200);

        // Cannot create/modify GL data
        $this->makeRequest('POST', '/api/gl/journals', [
            'description' => 'Audit Journal',
            'source' => 'manual',
            'lines' => [],
        ], $user)->assertStatus(403);

        // Cannot access telebirr operations
        $this->makeRequest('GET', '/api/telebirr/agents', $user)->assertStatus(403);
        $this->makeRequest('POST', '/api/telebirr/transactions/topup', [], $user)->assertStatus(403);
        $this->makeRequest('GET', '/api/telebirr/transactions', $user)->assertStatus(403);
    }

    // ==================== CROSS-ROLE VERIFICATION TESTS ====================

    /** @test */
    public function verify_role_isolation_across_all_operations()
    {
        $roles = ['admin', 'manager', 'telebirr_distributor', 'sales', 'finance', 'inventory', 'audit'];

        foreach ($roles as $roleSlug) {
            $user = $this->users[$roleSlug];

            // Test that each role gets expected responses for key operations
            $this->verifyRoleCapabilities($user, $roleSlug);
        }
    }

    protected function verifyRoleCapabilities($user, $roleSlug)
    {
        $capabilities = [
            'admin' => [
                'telebirr_agents_read' => 200,
                'telebirr_agents_create' => 201,
                'telebirr_transactions_read' => 200,
                'telebirr_topup_create' => 200,
                'gl_journals_read' => 200,
                'gl_journals_create' => 201,
            ],
            'manager' => [
                'telebirr_agents_read' => 200,
                'telebirr_agents_create' => 403,
                'telebirr_transactions_read' => 200,
                'telebirr_topup_create' => 403,
                'gl_journals_read' => 200,
                'gl_journals_create' => 403,
            ],
            'telebirr_distributor' => [
                'telebirr_agents_read' => 200,
                'telebirr_agents_create' => 403,
                'telebirr_transactions_read' => 200,
                'telebirr_topup_create' => 200,
                'gl_journals_read' => 403,
                'gl_journals_create' => 403,
            ],
            'sales' => [
                'telebirr_agents_read' => 200,
                'telebirr_agents_create' => 403,
                'telebirr_transactions_read' => 200,
                'telebirr_topup_create' => 403,
                'gl_journals_read' => 403,
                'gl_journals_create' => 403,
            ],
            'finance' => [
                'telebirr_agents_read' => 200,
                'telebirr_agents_create' => 403,
                'telebirr_transactions_read' => 200,
                'telebirr_topup_create' => 200,
                'gl_journals_read' => 200,
                'gl_journals_create' => 201,
            ],
            'inventory' => [
                'telebirr_agents_read' => 403,
                'telebirr_agents_create' => 403,
                'telebirr_transactions_read' => 403,
                'telebirr_topup_create' => 403,
                'gl_journals_read' => 403,
                'gl_journals_create' => 403,
            ],
            'audit' => [
                'telebirr_agents_read' => 403,
                'telebirr_agents_create' => 403,
                'telebirr_transactions_read' => 403,
                'telebirr_topup_create' => 403,
                'gl_journals_read' => 200,
                'gl_journals_create' => 403,
            ],
        ];

        $expected = $capabilities[$roleSlug];

        // Test telebirr agents read
        $this->makeRequest('GET', '/api/telebirr/agents', $user)
            ->assertStatus($expected['telebirr_agents_read']);

        // Test telebirr agents create
        if ($expected['telebirr_agents_create'] === 201) {
            $this->makeRequest('POST', '/api/telebirr/agents', [
                'name' => 'Test Agent ' . $roleSlug,
                'short_code' => 'TST' . strtoupper(substr($roleSlug, 0, 3)),
                'phone' => '+251911123456',
                'location' => 'Addis Ababa',
                'status' => 'Active',
            ], $user)->assertStatus($expected['telebirr_agents_create']);
        } else {
            $this->makeRequest('POST', '/api/telebirr/agents', [
                'name' => 'Test Agent ' . $roleSlug,
                'short_code' => 'TST' . strtoupper(substr($roleSlug, 0, 3)),
                'phone' => '+251911123456',
                'location' => 'Addis Ababa',
                'status' => 'Active',
            ], $user)->assertStatus($expected['telebirr_agents_create']);
        }

        // Test telebirr transactions read
        $this->makeRequest('GET', '/api/telebirr/transactions', $user)
            ->assertStatus($expected['telebirr_transactions_read']);

        // Test telebirr topup create
        $this->makeRequest('POST', '/api/telebirr/transactions/topup', [
            'agent_id' => $this->testData['agent']->id,
            'amount' => 1000,
            'currency' => 'ETB',
        ], $user)->assertStatus($expected['telebirr_topup_create']);

        // Test GL journals read
        $this->makeRequest('GET', '/api/gl/journals', $user)
            ->assertStatus($expected['gl_journals_read']);

        // Test GL journals create
        if ($expected['gl_journals_create'] === 201) {
            $this->makeRequest('POST', '/api/gl/journals', [
                'description' => 'Test Journal ' . $roleSlug,
                'source' => 'manual',
                'lines' => [
                    [
                        'account_id' => $this->testData['glAccount']->id,
                        'debit' => 1000,
                        'credit' => 0,
                        'description' => 'Test debit',
                    ],
                    [
                        'account_id' => $this->testData['glAccount']->id,
                        'debit' => 0,
                        'credit' => 1000,
                        'description' => 'Test credit',
                    ],
                ],
            ], $user)->assertStatus($expected['gl_journals_create']);
        } else {
            $this->makeRequest('POST', '/api/gl/journals', [
                'description' => 'Test Journal ' . $roleSlug,
                'source' => 'manual',
                'lines' => [],
            ], $user)->assertStatus($expected['gl_journals_create']);
        }
    }
}