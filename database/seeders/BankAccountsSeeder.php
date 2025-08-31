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
            [
                'name' => 'ABASINIYA 101954943',
                'external_number' => '101954943',
                'gl_account_code' => '1104',
            ],
            [
                'name' => 'AWASH 01410991463100',
                'external_number' => '01410991463100',
                'gl_account_code' => '1105',
            ],
            [
                'name' => 'DASHIN 7914329202011',
                'external_number' => '7914329202011',
                'gl_account_code' => '1106',
            ],
            [
                'name' => 'TELEBIRR 0963373333',
                'external_number' => '0963373333',
                'gl_account_code' => '1107',
            ],
            [
                'name' => 'ESAHAL 0963373333',
                'external_number' => '0963373333',
                'gl_account_code' => '1108',
            ],
            [
                'name' => 'H.CASH 0963373333',
                'external_number' => '0963373333',
                'gl_account_code' => '1110',
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
