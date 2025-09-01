<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Capability;
use App\Models\UserRoleAssignment;
use App\Models\UserPolicy;
use App\Models\Product;
use App\Models\Branch;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class RbacTaskComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected $users = [];
    protected $testData = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the database with our new RBAC setup
        $this->seedDatabase();

        // Create test users for each role
        $this->createTestUsers();

        // Create test data
        $this->createTestData();
    }

    protected function seedDatabase()
    {
        // Run the database seeder to set up capabilities and roles
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);
    }

    protected function createTestUsers()
    {
        $roles = ['admin', 'finance', 'sales', 'auditor', 'api_client'];

        foreach ($roles as $roleSlug) {
            $user = User::firstOrCreate([
                'email' => $roleSlug . '@example.com',
            ], [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'name' => ucfirst($roleSlug) . ' User',
                'password' => bcrypt('password'),
            ]);

            $role = Role::where('slug', $roleSlug)->first();
            if ($role) {
                UserRoleAssignment::firstOrCreate([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'branch_id' => null,
                ]);
            }

            $this->users[$roleSlug] = $user;
        }

        // Rebuild RBAC cache for all users
        app(\App\Domain\Auth\RbacCacheBuilder::class)->rebuildAll();
    }

    protected function createTestData()
    {
        $this->testData['product'] = Product::factory()->create();
        $this->testData['branch'] = Branch::factory()->create(['code' => 'test_branch']);
    }

    // ==================== POSITIVE TESTS ====================

    /** @test */
    public function admin_can_perform_all_operations()
    {
        $user = $this->users['admin'];

        // Products
        $this->actingAs($user)->get('/api/products')->assertStatus(200);
        $this->actingAs($user)->post('/api/products', [
            'code' => 'TEST001',
            'name' => 'Test Product',
            'type' => 'OTHER',
            'uom' => 'pcs',
            'is_active' => true,
        ])->assertStatus(201);
        $this->actingAs($user)->patch('/api/products/' . $this->testData['product']->id, [
            'name' => 'Updated Product'
        ])->assertStatus(200);

        // Inventory
        $this->actingAs($user)->get('/api/inventory')->assertStatus(200);
        $this->actingAs($user)->post('/api/inventory/receive', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 100,
        ])->assertStatus(200);
        $this->actingAs($user)->post('/api/inventory/adjust', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 50,
        ])->assertStatus(200);

        // Reports
        $this->actingAs($user)->get('/api/reports/summary')->assertStatus(200);

        // Audit
        $this->actingAs($user)->get('/api/audit/logs')->assertStatus(200);
    }

    /** @test */
    public function finance_can_perform_inventory_operations()
    {
        $user = $this->users['finance'];

        // Can read products
        $this->actingAs($user)->get('/api/products')->assertStatus(200);

        // Cannot create/update products
        $this->actingAs($user)->post('/api/products', [
            'code' => 'TEST002',
            'name' => 'Test Product 2',
            'type' => 'OTHER',
            'uom' => 'pcs',
            'is_active' => true,
        ])->assertStatus(403);
        $this->actingAs($user)->patch('/api/products/' . $this->testData['product']->id, [
            'name' => 'Updated Product'
        ])->assertStatus(403);

        // Can perform all inventory operations
        $this->actingAs($user)->post('/api/inventory/receive', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 100,
        ])->assertStatus(200);
        $this->actingAs($user)->post('/api/inventory/reserve', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 10,
        ])->assertStatus(200);
        $this->actingAs($user)->post('/api/inventory/unreserve', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 5,
        ])->assertStatus(200);
        $this->actingAs($user)->post('/api/inventory/issue', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 20,
        ])->assertStatus(200);
        $this->actingAs($user)->post('/api/inventory/transfer', [
            'product_id' => $this->testData['product']->id,
            'from_branch_id' => $this->testData['branch']->id,
            'to_branch_id' => Branch::factory()->create(['code' => 'dest_branch'])->id,
            'qty' => 15,
        ])->assertStatus(200);
        $this->actingAs($user)->post('/api/inventory/adjust', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 50,
        ])->assertStatus(200);

        // Cannot view audit logs
        $this->actingAs($user)->get('/api/audit/logs')->assertStatus(403);
    }

    /** @test */
    public function sales_can_perform_pos_inventory_operations()
    {
        $user = $this->users['sales'];

        // Can read products
        $this->actingAs($user)->get('/api/products')->assertStatus(200);

        // Cannot create/update products
        $this->actingAs($user)->post('/api/products', [
            'code' => 'TEST003',
            'name' => 'Test Product 3',
            'type' => 'OTHER',
            'uom' => 'pcs',
            'is_active' => true,
        ])->assertStatus(403);
        $this->actingAs($user)->patch('/api/products/' . $this->testData['product']->id, [
            'name' => 'Updated Product'
        ])->assertStatus(403);

        // Can reserve and issue inventory (POS operations)
        $this->actingAs($user)->post('/api/inventory/reserve', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 10,
        ])->assertStatus(200);
        $this->actingAs($user)->post('/api/inventory/issue', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 5,
        ])->assertStatus(200);

        // Cannot perform other inventory operations
        $this->actingAs($user)->post('/api/inventory/receive', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 100,
        ])->assertStatus(403);
        $this->actingAs($user)->post('/api/inventory/transfer', [
            'product_id' => $this->testData['product']->id,
            'from_branch_id' => $this->testData['branch']->id,
            'to_branch_id' => Branch::factory()->create(['code' => 'dest_branch2'])->id,
            'qty' => 15,
        ])->assertStatus(403);
        $this->actingAs($user)->post('/api/inventory/adjust', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 50,
        ])->assertStatus(403);
    }

    /** @test */
    public function auditor_can_read_reports_and_audit_logs()
    {
        $user = $this->users['auditor'];

        // Can read products
        $this->actingAs($user)->get('/api/products')->assertStatus(200);

        // Can view reports
        $this->actingAs($user)->get('/api/reports/summary')->assertStatus(200);
        $this->actingAs($user)->get('/api/reports/inventory')->assertStatus(200);
        $this->actingAs($user)->get('/api/reports/products')->assertStatus(200);

        // Can view audit logs
        $this->actingAs($user)->get('/api/audit/logs')->assertStatus(200);

        // Cannot perform any mutations
        $this->actingAs($user)->post('/api/products', [
            'code' => 'TEST004',
            'name' => 'Test Product 4',
            'type' => 'OTHER',
            'uom' => 'pcs',
            'is_active' => true,
        ])->assertStatus(403);
        $this->actingAs($user)->post('/api/inventory/receive', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 100,
        ])->assertStatus(403);
    }

    // ==================== NEGATIVE TESTS ====================

    /** @test */
    public function sales_cannot_perform_finance_inventory_operations()
    {
        $user = $this->users['sales'];

        // Cannot receive inventory
        $this->actingAs($user)->post('/api/inventory/receive', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 100,
        ])->assertStatus(403);

        // Cannot transfer inventory
        $this->actingAs($user)->post('/api/inventory/transfer', [
            'product_id' => $this->testData['product']->id,
            'from_branch_id' => $this->testData['branch']->id,
            'to_branch_id' => Branch::factory()->create(['code' => 'dest_branch3'])->id,
            'qty' => 15,
        ])->assertStatus(403);

        // Cannot adjust inventory
        $this->actingAs($user)->post('/api/inventory/adjust', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 50,
        ])->assertStatus(403);
    }

    /** @test */
    public function auditor_cannot_perform_mutations()
    {
        $user = $this->users['auditor'];

        // Cannot create products
        $this->actingAs($user)->post('/api/products', [
            'code' => 'TEST005',
            'name' => 'Test Product 5',
            'type' => 'OTHER',
            'uom' => 'pcs',
            'is_active' => true,
        ])->assertStatus(403);

        // Cannot update products
        $this->actingAs($user)->patch('/api/products/' . $this->testData['product']->id, [
            'name' => 'Updated Product'
        ])->assertStatus(403);

        // Cannot perform any inventory operations
        $this->actingAs($user)->post('/api/inventory/receive', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 100,
        ])->assertStatus(403);
        $this->actingAs($user)->post('/api/inventory/reserve', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 10,
        ])->assertStatus(403);
    }

    /** @test */
    public function finance_cannot_view_audit_logs()
    {
        $user = $this->users['finance'];

        // Cannot view audit logs (as per task requirements)
        $this->actingAs($user)->get('/api/audit/logs')->assertStatus(403);
    }

    // ==================== AUDIT LOGGING TESTS ====================

    /** @test */
    public function rbac_denials_are_logged()
    {
        $user = $this->users['sales'];

        // Attempt operation that should be denied
        $this->actingAs($user)->post('/api/inventory/receive', [
            'product_id' => $this->testData['product']->id,
            'branch_id' => $this->testData['branch']->id,
            'qty' => 100,
        ])->assertStatus(403);

        // Check that audit log was created
        $auditLog = AuditLog::where('action', 'rbac.check')
            ->where('user_id', $user->id)
            ->where('details->result', 'denied')
            ->where('details->capability', 'inventory.receive')
            ->first();

        $this->assertNotNull($auditLog, 'RBAC denial should be logged');
        $this->assertEquals('denied', $auditLog->details['result']);
        $this->assertEquals('inventory.receive', $auditLog->details['capability']);
    }

    /** @test */
    public function rbac_grants_are_logged()
    {
        $user = $this->users['sales'];

        // Attempt operation that should be allowed
        $this->actingAs($user)->get('/api/products')->assertStatus(200);

        // Check that audit log was created
        $auditLog = AuditLog::where('action', 'rbac.check')
            ->where('user_id', $user->id)
            ->where('details->result', 'granted')
            ->where('details->capability', 'products.read')
            ->first();

        $this->assertNotNull($auditLog, 'RBAC grant should be logged');
        $this->assertEquals('granted', $auditLog->details['result']);
        $this->assertEquals('products.read', $auditLog->details['capability']);
    }

    // ==================== BRANCH-SPECIFIC TESTS ====================

    /** @test */
    public function branch_specific_permissions_work()
    {
        $user = $this->users['finance'];
        $branch1 = $this->testData['branch'];
        $branch2 = Branch::factory()->create(['code' => 'branch2']);

        // Assign user to specific branch
        UserRoleAssignment::where('user_id', $user->id)->update(['branch_id' => $branch1->id]);

        // Rebuild user policies
        app(\App\Domain\Auth\RbacCacheBuilder::class)->rebuildForUser($user);

        // Should work with correct branch
        $this->actingAs($user)
            ->withHeader('X-Branch-Id', $branch1->id)
            ->post('/api/inventory/receive', [
                'product_id' => $this->testData['product']->id,
                'branch_id' => $branch1->id,
                'qty' => 100,
            ])->assertStatus(200);

        // Should fail with different branch
        $this->actingAs($user)
            ->withHeader('X-Branch-Id', $branch2->id)
            ->post('/api/inventory/receive', [
                'product_id' => $this->testData['product']->id,
                'branch_id' => $branch1->id,
                'qty' => 100,
            ])->assertStatus(403);
    }

    /** @test */
    public function global_permissions_work_without_branch_header()
    {
        $user = $this->users['admin'];

        // Admin has global permissions
        $this->actingAs($user)->get('/api/products')->assertStatus(200);
        $this->actingAs($user)->get('/api/inventory')->assertStatus(200);
    }
}