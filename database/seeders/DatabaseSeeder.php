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
            ['slug' => 'finance', 'name' => 'Finance', 'is_system' => true],
            ['slug' => 'sales', 'name' => 'Sales', 'is_system' => true],
            ['slug' => 'auditor', 'name' => 'Auditor', 'is_system' => true],
            ['slug' => 'api_client', 'name' => 'API Client', 'is_system' => true],
        ];
        $roleModels = [];
        foreach ($roles as $role) {
            $roleModels[$role['slug']] = Role::firstOrCreate(['slug' => $role['slug']], $role);
        }

        // 2. Create Capabilities
        $capabilities = [
            // Products capabilities
            ['name' => 'Read Products', 'key' => 'products.read', 'group' => 'products'],
            ['name' => 'Create Products', 'key' => 'products.create', 'group' => 'products'],
            ['name' => 'Update Products', 'key' => 'products.update', 'group' => 'products'],

            // Inventory capabilities
            ['name' => 'Read Inventory', 'key' => 'inventory.read', 'group' => 'inventory'],
            ['name' => 'Receive Inventory', 'key' => 'inventory.receive', 'group' => 'inventory'],
            ['name' => 'Reserve Inventory', 'key' => 'inventory.reserve', 'group' => 'inventory'],
            ['name' => 'Unreserve Inventory', 'key' => 'inventory.unreserve', 'group' => 'inventory'],
            ['name' => 'Issue Inventory', 'key' => 'inventory.issue', 'group' => 'inventory'],
            ['name' => 'Transfer Inventory', 'key' => 'inventory.transfer', 'group' => 'inventory'],
            ['name' => 'Adjust Inventory', 'key' => 'inventory.adjust', 'group' => 'inventory'],

            // Reports capabilities
            ['name' => 'View Reports', 'key' => 'reports.view', 'group' => 'reports'],

            // Audit capabilities
            ['name' => 'View Audit Logs', 'key' => 'audit.view', 'group' => 'audit'],

            // Additional capabilities for completeness
            ['name' => 'Manage Users', 'key' => 'users.manage', 'group' => 'users'],
            ['name' => 'Read Transactions', 'key' => 'tx.read', 'group' => 'transactions'],
            ['name' => 'Create Transactions', 'key' => 'tx.create', 'group' => 'transactions'],
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
            'finance' => [ // Finance role: inventory operations + products.read, no products.create/update, no audit.view
                'inventory.receive',
                'inventory.reserve',
                'inventory.unreserve',
                'inventory.issue',
                'inventory.transfer',
                'inventory.adjust',
                'products.read',
                'tx.read',
                'tx.create',
                'receipts.create',
                'receipts.void',
            ],
            'sales' => [ // Sales role: reserve/issue via POS + products.read, no receive/transfer/adjust
                'inventory.reserve',
                'inventory.issue',
                'products.read',
                'tx.read',
                'tx.create',
                'receipts.create',
            ],
            'auditor' => [ // Auditor role: reports.view, audit.view, products.read, no mutations
                'reports.view',
                'audit.view',
                'products.read',
                'tx.read',
            ],
            'api_client' => [ // API Client: scoped to configured capabilities (will be set per client)
                'products.read',
                'inventory.read',
                'tx.read',
            ],
        ];
        foreach ($roleCaps as $roleSlug => $capKeys) {
            $role = $roleModels[$roleSlug];
            $capIds = array_map(fn($key) => $capModels[$key]->id, $capKeys);
            $role->capabilities()->syncWithoutDetaching($capIds);
        }

        // 4. Create Branches (static + factory for realistic data)
        $branches = [
            ['name' => 'Main', 'code' => 'main'],
            ['name' => 'Hamada', 'code' => 'hamada'],
            ['name' => 'Chinaksen', 'code' => 'chinaksen'],
        ];
        $branchModels = [];
        foreach ($branches as $branch) {
            $branchModels[$branch['code']] = Branch::firstOrCreate(['code' => $branch['code']], $branch);
        }
        // Add more branches via factory
        Branch::factory()->count(5)->create();

        // 4b. Create Products (static + factory for realistic data)
        $products = [
            ['id' => (string) Str::uuid(), 'code' => 'SIM-PREP-4G', 'name' => 'SIM Card 4G', 'type' => 'SIM', 'uom' => 'pcs', 'is_active' => true, 'meta' => ['iccid_range' => '8901000000000000000-8901000000000009999']],
            ['id' => (string) Str::uuid(), 'code' => 'VCH-100', 'name' => 'Voucher 100', 'type' => 'VOUCHER', 'uom' => 'card', 'is_active' => true, 'meta' => ['serials' => ['VCH001', 'VCH002'], 'batch' => 'BATCH001']],
            ['id' => (string) Str::uuid(), 'code' => 'EVD-TOPUP', 'name' => 'EVD Topup', 'type' => 'EVD', 'uom' => 'amount', 'is_active' => true],
            ['id' => (string) Str::uuid(), 'code' => 'AIR-ET', 'name' => 'Ethio Telecom Airtime', 'type' => 'E_AIRTIME', 'uom' => 'amount', 'is_active' => true],
            ['id' => (string) Str::uuid(), 'code' => 'TB-CASHIN', 'name' => 'Telebirr Cash In', 'type' => 'TELEBIRR', 'uom' => 'amount', 'is_active' => true],
            ['id' => (string) Str::uuid(), 'code' => 'YIM-SVC', 'name' => 'Yimulu Service', 'type' => 'YIMULU', 'uom' => 'card', 'is_active' => true],
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
            ['name' => 'nimco', 'email' => 'finance@example.com', 'password' => bcrypt('secret123'), 'role' => 'finance', 'branch' => 'main'],
            ['name' => 'hamze', 'email' => 'sales@example.com', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'main'],
            ['name' => 'yasmin', 'email' => 'auditor@example.com', 'password' => bcrypt('secret123'), 'role' => 'auditor', 'branch' => 'main'],
            ['name' => 'api_client', 'email' => 'api@example.com', 'password' => bcrypt('secret123'), 'role' => 'api_client', 'branch' => 'main'],
            // Variants
            ['name' => 'Disabled User', 'email' => 'disabled@example.com', 'password' => bcrypt('secret123'), 'role' => null, 'branch' => null], // No role - disabled
            ['name' => 'Expired Token User', 'email' => 'expired@example.com', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'main'],
            ['name' => 'Wrong Branch User', 'email' => 'wrongbranch@example.com', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'hamada'], // Assigned to hamada but maybe should be main
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
            InventorySeeder::class,
            ChartOfAccountsSeeder::class,
            BankAccountsSeeder::class,
            TelebirrAgentsSeeder::class,
            OpeningBalancesSeeder::class,
        ]);
    }
}
