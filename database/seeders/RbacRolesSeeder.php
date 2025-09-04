<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Capability;

class RbacRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $roles = [
            'administrator' => 'Administrator',
            'sales-restricted' => 'Sales (restricted)',
            'inventory-ops' => 'Inventory (store keeper, sales & cashier)',
            'finance-audit' => 'Finance & Audit',
            'telebirr-sales-only' => 'Telebirr Sales (Distributor Only)',
        ];

        $roleModels = [];
        foreach ($roles as $slug => $name) {
            $roleModels[$slug] = Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'is_system' => true]);
        }

        // Get capabilities
        $caps = Capability::all()->keyBy('key');

        // Assign capabilities
        $roleCaps = [
            'administrator' => array_keys($caps->toArray()), // All capabilities
            'sales-restricted' => [
                'pos.view',
                'pos.create',
                'pos.checkout',
                'inventory.view',
                'inventory.receive',
                'inventory.transfer.create',
                'products.view',
                'products.create',
                'reports.view',
                'reports.sales.view',
                'reports.inventory.view',
            ],
            'inventory-ops' => [
                'inventory.view',
                'inventory.receive',
                'inventory.adjust',
                'inventory.transfer.create',
                'pos.view',
                'pos.create',
                'cashbox.open',
                'cashbox.close',
                'receipts.create',
                'products.view',
                'reports.view',
            ],
            'finance-audit' => [
                'finance.view',
                'finance.post',
                'journals.create',
                'payments.create',
                'reports.view',
                'reports.finance.view',
                'audit.logs.view',
                'products.view',
                'inventory.view',
                'customers.view',
            ],
            'telebirr-sales-only' => [
                'telebirr.view',
                'telebirr.sales.create',
                'telebirr.settlement.view',
                'telebirr.agents.view',
            ],
        ];

        foreach ($roleCaps as $roleSlug => $capKeys) {
            $role = $roleModels[$roleSlug];
            $capIds = [];
            foreach ($capKeys as $key) {
                if (isset($caps[$key])) {
                    $capIds[] = $caps[$key]->id;
                }
            }
            $role->capabilities()->sync($capIds);
        }
    }
}