<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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
            ['name' => 'View Transactions', 'key' => 'tx.view', 'group' => 'transactions'],
            ['name' => 'Create Transaction', 'key' => 'tx.create', 'group' => 'transactions'],
            ['name' => 'Approve Transaction', 'key' => 'tx.approve', 'group' => 'transactions'],
            ['name' => 'Manage Users', 'key' => 'users.manage', 'group' => 'users'],
            ['name' => 'Manage Inventory', 'key' => 'inventory.manage', 'group' => 'inventory'],
            ['name' => 'View Inventory', 'key' => 'inventory.view', 'group' => 'inventory'],
            ['name' => 'Audit Logs', 'key' => 'audit.logs', 'group' => 'audit'],
            ['name' => 'Telebirr Sales', 'key' => 'telebirr.sales', 'group' => 'telebirr'],
            ['name' => 'Finance Access', 'key' => 'finance.access', 'group' => 'finance'],
            // Telebirr specific capabilities
            ['name' => 'View Agents', 'key' => 'agents.read', 'group' => 'telebirr'],
            ['name' => 'Create Agents', 'key' => 'agents.create', 'group' => 'telebirr'],
            ['name' => 'Update Agents', 'key' => 'agents.update', 'group' => 'telebirr'],
            ['name' => 'Issue E-float', 'key' => 'efloat.issue', 'group' => 'telebirr'],
            ['name' => 'Loan E-float', 'key' => 'efloat.loan', 'group' => 'telebirr'],
            ['name' => 'Create Topup', 'key' => 'topup.create', 'group' => 'telebirr'],
            ['name' => 'Create Repayment', 'key' => 'repayment.create', 'group' => 'telebirr'],
            ['name' => 'EBIRR Reconciliation', 'key' => 'recon.ebirr', 'group' => 'telebirr'],
            ['name' => 'Void Voucher', 'key' => 'voucher.void', 'group' => 'telebirr'],
            ['name' => 'View Reports', 'key' => 'reports.view', 'group' => 'telebirr'],
            // GL (General Ledger) Capabilities
            ['name' => 'View GL Journals', 'key' => 'gl.view', 'group' => 'general_ledger'],
            ['name' => 'Create GL Journals', 'key' => 'gl.create', 'group' => 'general_ledger'],
            ['name' => 'Post GL Journals', 'key' => 'gl.post', 'group' => 'general_ledger'],
            ['name' => 'Reverse GL Journals', 'key' => 'gl.reverse', 'group' => 'general_ledger'],
            ['name' => 'Manage GL Accounts', 'key' => 'gl.manage_accounts', 'group' => 'general_ledger'],
            ['name' => 'Manage GL Journal Sources', 'key' => 'gl.manage_journal_sources', 'group' => 'general_ledger'],
        ];
        $capModels = [];
        foreach ($capabilities as $cap) {
            $capModels[$cap['key']] = Capability::firstOrCreate(['key' => $cap['key']], $cap);
        }

        // 3. Attach Capabilities to Roles
        $roleCaps = [
            'admin' => array_keys($capModels),
            'manager' => ['tx.view', 'tx.create', 'tx.approve', 'users.manage', 'inventory.view', 'inventory.manage', 'finance.access', 'gl.view', 'agents.read', 'agents.create', 'agents.update', 'efloat.issue', 'efloat.loan', 'topup.create', 'repayment.create', 'recon.ebirr', 'voucher.void', 'reports.view'],
            'sales' => ['tx.view', 'tx.create', 'telebirr.sales'],
            'telebirr_distributor' => ['telebirr.sales', 'efloat.issue', 'efloat.loan', 'agents.read', 'reports.view'],
            'finance' => ['finance.access', 'tx.view', 'gl.view', 'gl.create', 'gl.post', 'gl.reverse', 'topup.create', 'repayment.create', 'recon.ebirr', 'voucher.void', 'reports.view'],
            'inventory' => ['inventory.view', 'inventory.manage'],
            'audit' => ['audit.logs', 'gl.view'],
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
            ['name' => 'Najib hassen', 'email' => 'najib@najibshop.shop', 'password' => bcrypt('secret123'), 'role' => 'manager', 'branch' => 'main'],
            ['name' => 'hafsa hassen', 'email' => 'hafsa@najibshop.shop', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'main'],
            ['name' => 'Marwan haji', 'email' => 'marwan@najibshop.shop', 'password' => bcrypt('secret123'), 'role' => 'inventory', 'branch' => 'main'],
            ['name' => 'hawa kabade', 'email' => 'hawa@najibshop.shop', 'password' => bcrypt('secret123'), 'role' => 'finance', 'branch' => 'main'],
            ['name' => 'hana hasen', 'email' => 'hana@najibshop.shop', 'password' => bcrypt('secret123'), 'role' => 'telebirr_distributor', 'branch' => 'main'],
            // Hamda Hotel Branch
            ['name' => 'Naila haji', 'email' => 'naila@najibshop.shop', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'hamda'],
            // Chinaksan Branch
            ['name' => 'Yenesew mekonin', 'email' => 'yenesew@najibshop.shop', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'chinaksan'],
        ];
        foreach ($users as $u) {
            $user = User::firstOrCreate(['email' => $u['email']], [
                'name' => $u['name'],
                'password' => $u['password'],
            ]);
            UserRoleAssignment::firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $roleModels[$u['role']]->id,
                'branch_id' => $branchModels[$u['branch']]->id,
            ]);
        }

        // 6. Create or fetch superuser and assign admin role (no branch)
        $superuser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Super Admin', 'password' => bcrypt('secret123')]
        );
        UserRoleAssignment::firstOrCreate([
            'user_id' => $superuser->id,
            'role_id' => $roleModels['admin']->id,
            'branch_id' => null,
        ]);

        // Call additional seeders
        $this->call([
            BranchSeeder::class,
            ProductSeeder::class,
            TelebirrGlAccountsSeeder::class,
            BankAccountsSeeder::class,
        ]);
    }
}
