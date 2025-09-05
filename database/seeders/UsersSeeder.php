<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Branch;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Str;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Get branches
        $branches = Branch::all()->keyBy('code');

        // Get roles
        $roles = Role::all()->keyBy('slug');

        // Users data
        $users = [
            [
                'name' => 'Ismail',
                'email' => 'ismail@najib.shop',
                'role' => 'admin',
                'branch' => 'main',
                'password' => 'N*j1b!Ismail#2025'
            ],
            [
                'name' => 'Najib Hassen',
                'email' => 'najib.hassen@najib.shop',
                'role' => 'admin',
                'branch' => 'main',
                'password' => 'N*j1b!Najib#2025'
            ],
            [
                'name' => 'Hafsa Hassen',
                'email' => 'hafsa.hassen@najib.shop',
                'role' => 'sales',
                'branch' => 'main',
                'password' => 'N*j1b!Hafsa#2025'
            ],
            [
                'name' => 'Marwan Haji',
                'email' => 'marwan.haji@najib.shop',
                'role' => 'finance',
                'branch' => 'main',
                'password' => 'N*j1b!Marwan#2025'
            ],
            [
                'name' => 'Hawa Kabade',
                'email' => 'hawa.kabade@najib.shop',
                'role' => 'auditor',
                'branch' => 'main',
                'password' => 'N*j1b!Hawa#2025'
            ],
            [
                'name' => 'Hana Hasen',
                'email' => 'hana.hasen@najib.shop',
                'role' => 'telebirr_distributor',
                'branch' => 'main',
                'password' => 'N*j1b!Hana#2025'
            ],
            [
                'name' => 'Naila Haji',
                'email' => 'naila.haji@najib.shop',
                'role' => 'sales',
                'branch' => 'hamada',
                'password' => 'N*j1b!Naila#2025'
            ],
            [
                'name' => 'Yenesew Mekonin',
                'email' => 'yenesew.mekonin@najib.shop',
                'role' => 'sales',
                'branch' => 'chinaksen',
                'password' => 'N*j1b!Yenesew#2025'
            ],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $u['name'],
                    'password' => bcrypt($u['password']),
                    'email_verified_at' => now(),
                ]
            );

            if (isset($roles[$u['role']]) && isset($branches[$u['branch']])) {
                UserRoleAssignment::firstOrCreate([
                    'user_id' => $user->id,
                    'role_id' => $roles[$u['role']]->id,
                    'branch_id' => $branches[$u['branch']]->id,
                ]);
            }
        }
    }
}