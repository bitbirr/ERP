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
        ];
        $capModels = [];
        foreach ($capabilities as $cap) {
            $capModels[$cap['key']] = Capability::firstOrCreate(['key' => $cap['key']], $cap);
        }

        // 3. Attach Capabilities to Roles
        $roleCaps = [
            'admin' => array_keys($capModels),
            'manager' => ['tx.view', 'tx.create', 'tx.approve', 'users.manage', 'inventory.view', 'inventory.manage', 'finance.access'],
            'sales' => ['tx.view', 'tx.create', 'telebirr.sales'],
            'telebirr_distributor' => ['telebirr.sales'],
            'finance' => ['finance.access', 'tx.view'],
            'inventory' => ['inventory.view', 'inventory.manage'],
            'audit' => ['audit.logs'],
        ];
        foreach ($roleCaps as $roleSlug => $capKeys) {
            $role = $roleModels[$roleSlug];
            $capIds = array_map(fn($key) => $capModels[$key]->id, $capKeys);
            $role->capabilities()->syncWithoutDetaching($capIds);
        }

        // 4. Create Branches
        $branches = [
            ['name' => 'Main Branch', 'code' => 'main'],
            ['name' => 'Hamda Hotel Branch', 'code' => 'hamda'],
            ['name' => 'Chinaksan Branch', 'code' => 'chinaksan'],
        ];
        $branchModels = [];
        foreach ($branches as $branch) {
            $branchModels[$branch['code']] = Branch::firstOrCreate(['code' => $branch['code']], $branch);
        }

        // 5. Create Users and Assign Roles/Branches
        $users = [
            // Main Branch
            ['name' => 'Najib hassen', 'email' => 'najib@main.com', 'password' => bcrypt('secret123'), 'role' => 'manager', 'branch' => 'main'],
            ['name' => 'hafsa hassen', 'email' => 'hafsa@main.com', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'main'],
            ['name' => 'Marwan haji', 'email' => 'marwan@main.com', 'password' => bcrypt('secret123'), 'role' => 'inventory', 'branch' => 'main'],
            ['name' => 'hawa kabade', 'email' => 'hawa@main.com', 'password' => bcrypt('secret123'), 'role' => 'finance', 'branch' => 'main'],
            ['name' => 'hana hasen', 'email' => 'hana@main.com', 'password' => bcrypt('secret123'), 'role' => 'telebirr_distributor', 'branch' => 'main'],
            // Hamda Hotel Branch
            ['name' => 'Naila haji', 'email' => 'naila@hamda.com', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'hamda'],
            // Chinaksan Branch
            ['name' => 'Yenesew mekonin', 'email' => 'yenesew@chinaksan.com', 'password' => bcrypt('secret123'), 'role' => 'sales', 'branch' => 'chinaksan'],
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
    }
}
