<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BankAccount;
use App\Models\GlAccount;

class BankAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bankAccounts = [
            [
                'name' => 'CBE 1000279015839',
                'external_number' => '1000279015839',
                'gl_account_code' => '1101',
            ],
            [
                'name' => 'EBIRR 401765',
                'external_number' => '401765',
                'gl_account_code' => '1102',
            ],
            [
                'name' => 'COOPAY 805856',
                'external_number' => '805856',
                'gl_account_code' => '1103',
            ],
        ];

        foreach ($bankAccounts as $account) {
            $glAccount = GlAccount::where('code', $account['gl_account_code'])->first();

            if ($glAccount) {
                BankAccount::firstOrCreate(
                    ['external_number' => $account['external_number']],
                    [
                        'name' => $account['name'],
                        'gl_account_id' => $glAccount->id,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
