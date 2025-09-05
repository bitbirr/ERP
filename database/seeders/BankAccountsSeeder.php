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
        $mainBranch = \App\Models\Branch::where('code', 'main')->first();
        $customer = \App\Models\Customer::first(); // Get first customer

        if (!$mainBranch || !$customer) {
            return; // Skip if main branch or customer doesn't exist
        }

        $bankAccounts = [
            [
                'name' => 'CBE 1000279015839',
                'external_number' => '1000279015839',
                'gl_account_code' => '1101',
                'account_type' => 'checking',
            ],
            [
                'name' => 'EBIRR 401765',
                'external_number' => '401765',
                'gl_account_code' => '1102',
                'account_type' => 'checking',
            ],
            [
                'name' => 'COOPAY 805856',
                'external_number' => '805856',
                'gl_account_code' => '1103',
                'account_type' => 'checking',
            ],
        ];

        foreach ($bankAccounts as $account) {
            $glAccount = GlAccount::where('code', $account['gl_account_code'])->first();

            if ($glAccount) {
                BankAccount::firstOrCreate(
                    ['external_number' => $account['external_number']],
                    [
                        'name' => $account['name'],
                        'account_number' => $account['external_number'],
                        'gl_account_id' => $glAccount->id,
                        'account_type' => $account['account_type'],
                        'branch_id' => $mainBranch->id,
                        'customer_id' => $customer->id,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
