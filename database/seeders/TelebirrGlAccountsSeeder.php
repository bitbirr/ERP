<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\GlAccount;

class TelebirrGlAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            // Bank accounts
            ['code' => '1101', 'name' => 'Bank – CBE', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '1102', 'name' => 'Bank – EBIRR', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '1103', 'name' => 'Bank – Coopay', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '1104', 'name' => 'Bank – Abasiniya', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '1105', 'name' => 'Bank – Awash', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '1106', 'name' => 'Bank – Dashin', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '1107', 'name' => 'Bank – Telebirr', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '1108', 'name' => 'Bank – Esahal', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '1110', 'name' => 'Hand Cash', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],

            // Telebirr Distributor (credit-balance)
            ['code' => '1200', 'name' => 'Telebirr Distributor', 'type' => 'LIABILITY', 'normal_balance' => 'CREDIT'],

            // AR – Agents (subledger by agent)
            ['code' => '1300', 'name' => 'AR – Agents', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],

            // AR – EBIRR (clearing)
            ['code' => '1312', 'name' => 'AR – EBIRR', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
        ];

        foreach ($accounts as $account) {
            GlAccount::firstOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'normal_balance' => $account['normal_balance'],
                    'level' => 1,
                    'is_postable' => true,
                    'status' => 'ACTIVE',
                ]
            );
        }
    }
}
