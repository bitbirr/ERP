<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Role;
use App\Models\Capability;
use App\Models\Branch;
use App\Models\UserRoleAssignment;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Roles
        $roles = [
            ['slug' => 'admin', 'name' => 'Admin', 'is_system' => true],
            ['slug' => 'manager', 'name' => 'Manager', 'is_system' => true],
            ['slug' => 'sales', 'name' => 'Sales', 'is_system' => true],
            ['slug' => 'telebirr_distributor', 'name' => 'Telebirr Distributor', 'is_system' => true],
            ['slug' => 'finance', 'name' => 'Finance', 'is_system' => true],
            ['slug' => 'inventory', 'name' => 'Inventory', 'is_system' => true],
            ['slug' => 'audit', 'name' => 'Audit', 'is_system' => true],
        ];
        $roleModels = [];
        foreach ($roles as $role) {
            $roleModels[$role['slug']] = Role::firstOrCreate(['slug' => $role['slug']], $role);
        }

        // 2. Create Capabilities
        $capabilities = [
            // Telebirr capabilities
            ['name' => 'View Telebirr', 'key' => 'telebirr.view', 'group' => 'telebirr'],
            ['name' => 'Manage Telebirr', 'key' => 'telebirr.manage', 'group' => 'telebirr'],
            ['name' => 'Post Telebirr Transactions', 'key' => 'telebirr.post', 'group' => 'telebirr'],
            ['name' => 'Void Telebirr Transactions', 'key' => 'telebirr.void', 'group' => 'telebirr'],
            ['name' => 'Create Telebirr Topup', 'key' => 'telebirr.topup.create', 'group' => 'telebirr'],
            ['name' => 'Create Telebirr Issue', 'key' => 'telebirr.issue.create', 'group' => 'telebirr'],
            ['name' => 'Create Telebirr Loan', 'key' => 'telebirr.loan.create', 'group' => 'telebirr'],
            ['name' => 'Create Telebirr Repay', 'key' => 'telebirr.repay.create', 'group' => 'telebirr'],
            ['name' => 'Read Telebirr Transactions', 'key' => 'telebirr.tx.read', 'group' => 'telebirr'],
            ['name' => 'Read Telebirr Reports', 'key' => 'telebirr.report.read', 'group' => 'telebirr'],
            ['name' => 'View Telebirr Agents', 'key' => 'telebirr.agents.read', 'group' => 'telebirr'],
            ['name' => 'Create Telebirr Agents', 'key' => 'telebirr.agents.create', 'group' => 'telebirr'],
            ['name' => 'Update Telebirr Agents', 'key' => 'telebirr.agents.update', 'group' => 'telebirr'],
            ['name' => 'Void Telebirr Voucher', 'key' => 'telebirr.voucher.void', 'group' => 'telebirr'],
            ['name' => 'Telebirr Reconciliation', 'key' => 'telebirr.recon.ebirr', 'group' => 'telebirr'],

            // GL capabilities
            ['name' => 'View GL', 'key' => 'gl.view', 'group' => 'general_ledger'],
            ['name' => 'Create GL', 'key' => 'gl.create', 'group' => 'general_ledger'],
            ['name' => 'Post GL', 'key' => 'gl.post', 'group' => 'general_ledger'],
            ['name' => 'Reverse GL', 'key' => 'gl.reverse', 'group' => 'general_ledger'],
            ['name' => 'Manage GL Accounts', 'key' => 'gl.accounts.manage', 'group' => 'general_ledger'],
            ['name' => 'Manage GL Journal Sources', 'key' => 'gl.sources.manage', 'group' => 'general_ledger'],
            ['name' => 'Read GL Journals', 'key' => 'gl.journals.read', 'group' => 'general_ledger'],
            ['name' => 'Create GL Journals', 'key' => 'gl.journals.create', 'group' => 'general_ledger'],
            ['name' => 'Post GL Journals', 'key' => 'gl.post.create', 'group' => 'general_ledger'],
            ['name' => 'Reverse GL Journals', 'key' => 'gl.journals.reverse', 'group' => 'general_ledger'],

            // Audit capabilities
            ['name' => 'Read Audit Logs', 'key' => 'audit.read', 'group' => 'audit'],
            ['name' => 'Audit Logs', 'key' => 'audit.logs', 'group' => 'audit'],

            // User management
            ['name' => 'Manage Users', 'key' => 'users.manage', 'group' => 'users'],

            // Products capabilities
            ['name' => 'View Products', 'key' => 'products.view', 'group' => 'products'],
            ['name' => 'Manage Products', 'key' => 'products.manage', 'group' => 'products'],

            // Inventory capabilities
            ['name' => 'View Inventory', 'key' => 'inventory.view', 'group' => 'inventory'],
            ['name' => 'Manage Inventory', 'key' => 'inventory.manage', 'group' => 'inventory'],
            ['name' => 'Read Inventory', 'key' => 'inventory.read', 'group' => 'inventory'],

            // Finance capabilities
            ['name' => 'Finance Access', 'key' => 'finance.access', 'group' => 'finance'],

            // Transaction capabilities
            ['name' => 'Read Transactions', 'key' => 'tx.read', 'group' => 'transactions'],
            ['name' => 'Create Transactions', 'key' => 'tx.create', 'group' => 'transactions'],
            ['name' => 'Approve Transactions', 'key' => 'tx.approve', 'group' => 'transactions'],

            // POS/Receipt capabilities
            ['name' => 'Create Receipts', 'key' => 'receipts.create', 'group' => 'pos'],
            ['name' => 'Void Receipts', 'key' => 'receipts.void', 'group' => 'pos'],
        ];
        $capModels = [];
        foreach ($capabilities as $cap) {
            $capModels[$cap['key']] = Capability::firstOrCreate(['key' => $cap['key']], $cap);
        }

        // 3. Attach Capabilities to Roles
        $roleCaps = [
            'admin' => array_keys($capModels), // All capabilities
            'manager' => [ // Read/report access
                'products.view',
                'telebirr.view',
                'gl.view',
                'audit.read',
                'inventory.read',
                'tx.read',
                'receipts.void',
            ],
            'telebirr_distributor' => [ // Topup/issue/loan/repay
                'telebirr.view',
                'telebirr.post',
                'telebirr.void',
            ],
            'sales' => [ // Basic sales capabilities
                'tx.create',
                'tx.read',
                'telebirr.view',
                'receipts.create',
            ],
            'finance' => [ // Finance and GL capabilities
                'finance.access',
                'gl.view',
                'gl.create',
                'gl.post',
                'gl.reverse',
                'gl.accounts.manage',
                'gl.sources.manage',
                'telebirr.view',
                'telebirr.post',
                'telebirr.void',
                'tx.read',
                'receipts.void',
            ],
            'inventory' => [ // Inventory management
                'products.view',
                'inventory.read',
                'inventory.manage',
            ],
            'audit' => [ // Read-only audit access
                'audit.read',
                'audit.logs',
                'gl.view',
            ],
        ];
        foreach ($roleCaps as $roleSlug => $capKeys) {
            $role = $roleModels[$roleSlug];
            $capIds = array_map(fn($key) => $capModels[$key]->id, $capKeys);
            $role->capabilities()->syncWithoutDetaching($capIds);
        }

        // 4. Create Branches (static + factory for realistic data)
        $branches = [
            ['name' => 'Main Branch', 'code' => 'main'],
            ['name' => 'Hamda Hotel Branch', 'code' => 'hamda'],
            ['name' => 'Chinaksan Branch', 'code' => 'chinaksan'],
        ];
        $branchModels = [];
        foreach ($branches as $branch) {
            $branchModels[$branch['code']] = Branch::firstOrCreate(['code' => $branch['code']], $branch);
        }
        // Add more branches via factory
        Branch::factory()->count(5)->create();

        // 4b. Create Products (static + factory for realistic data)
        $products = [
            ['code' => 'P001', 'name' => 'Yimulu', 'type' => 'YIMULU', 'uom' => 'card', 'is_active' => true],
            ['code' => 'P002', 'name' => 'Voucher card', 'type' => 'VOUCHER', 'uom' => 'airtime', 'is_active' => true],
            ['code' => 'P003', 'name' => 'EVD', 'type' => 'EVD', 'uom' => 'pcs', 'is_active' => true],
            ['code' => 'P004', 'name' => 'simcard', 'type' => 'SIM', 'uom' => 'pcs', 'is_active' => true],
            ['code' => 'P005', 'name' => 'telebirr', 'type' => 'TELEBIRR', 'uom' => 'amount', 'is_active' => true],
        ];
        foreach ($products as $product) {
            \App\Models\Product::firstOrCreate(['code' => $product['code']], $product);
        }
        // Add more products via factory
        \App\Models\Product::factory()->count(20)->create();

        // 5. Create Users and Assign Roles/Branches
        $users = [
            // Main Branch
            ['name' => 'Ismail', 'email' => 'admin@example.com', 'password' => bcrypt('secret123'), 'role' => 'admin', 'branch' => 'main'],
            ['name' => 'Najo', 'email' => 'manager@example.com', 'password' => bcrypt('secret123'), 'role' => 'manager', 'branch' => 'main'],
            ['name' => 'mawlid', 'email' => 'distributor@example.com', 'password' => bcrypt('secret123'), 'role' => 'telebirr_distributor', 'branch' => 'main'],
            ['name' => 'hamze', 'email' => 'sales@example.com', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'main'],
            ['name' => 'nimco', 'email' => 'finance@example.com', 'password' => bcrypt('secret123'), 'role' => 'finance', 'branch' => 'main'],
            ['name' => 'ikran', 'email' => 'inventory@example.com', 'password' => bcrypt('secret123'), 'role' => 'inventory', 'branch' => 'main'],
            ['name' => 'yasmin', 'email' => 'audit@example.com', 'password' => bcrypt('secret123'), 'role' => 'audit', 'branch' => 'main'],
            // Variants
            ['name' => 'Disabled User', 'email' => 'disabled@example.com', 'password' => bcrypt('secret123'), 'role' => null, 'branch' => null], // No role - disabled
            ['name' => 'Expired Token User', 'email' => 'expired@example.com', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'main'],
            ['name' => 'Wrong Branch User', 'email' => 'wrongbranch@example.com', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'hamda'], // Assigned to hamda but maybe should be main
        ];
        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $u['name'],
                    'password' => $u['password'],
                ]
            );
            if ($u['role']) {
                UserRoleAssignment::firstOrCreate([
                    'user_id' => $user->id,
                    'role_id' => $roleModels[$u['role']]->id,
                    'branch_id' => $u['branch'] ? $branchModels[$u['branch']]->id : null,
                ]);
            }
            // Create tokens
            if ($u['email'] === 'expired@example.com') {
                $user->createToken('api', ['*'], now()->subDays(1)); // Expired token
            } else {
                $user->createToken('api'); // Valid token
            }
        }

        // 6. Create superuser with no branch (variant)
        $superuser = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Super Admin',
                'password' => bcrypt('secret123'),
            ]
        );
        UserRoleAssignment::firstOrCreate([
            'user_id' => $superuser->id,
            'role_id' => $roleModels['admin']->id,
            'branch_id' => null,
        ]);
        $superuser->createToken('api');

        // Call additional seeders
        $this->call([
            BranchesSeeder::class,
            ProductSeeder::class,
            ChartOfAccountsSeeder::class,
            BankAccountsSeeder::class,
            TelebirrAgentsSeeder::class,
            OpeningBalancesSeeder::class,
        ]);
    }
}
