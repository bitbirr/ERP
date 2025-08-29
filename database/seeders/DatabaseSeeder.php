<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
         $admin = \App\Models\Role::firstOrCreate(
        ['slug' => 'admin'],
        ['name' => 'Admin', 'is_system' => true]
    );

    $caps = collect([
        ['name' => 'View Transactions', 'key' => 'tx.view', 'group' => 'transactions'],
        ['name' => 'Create Transaction', 'key' => 'tx.create', 'group' => 'transactions'],
        ['name' => 'Approve Transaction', 'key' => 'tx.approve', 'group' => 'transactions'],
        ['name' => 'Manage Users', 'key' => 'users.manage', 'group' => 'users'],
    ])->map(function ($c) {
        return \App\Models\Capability::firstOrCreate(['key' => $c['key']], $c);
    });

    $admin->capabilities()->syncWithoutDetaching($caps->pluck('id')->all());

    $user = \App\Models\User::first() ?? \App\Models\User::factory()->create([
        'email' => 'admin@example.com',
        'password' => bcrypt('secret123'),
    ]);

    \App\Models\UserRoleAssignment::firstOrCreate([
        'user_id' => $user->id,
        'role_id' => $admin->id,
        'branch_id' => null,
    ]);
    }
}
