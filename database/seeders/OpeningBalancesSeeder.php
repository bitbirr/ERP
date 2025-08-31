<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GlJournal;
use App\Models\GlLine;
use App\Models\GlAccount;
use App\Models\BankAccount;
use Carbon\Carbon;

class OpeningBalancesSeeder extends Seeder
{
    public function run(): void
    {
        // Get required accounts
        $distributorAccount = GlAccount::where('code', '1200')->first();
        $cbeAccount = GlAccount::where('code', '1101')->first();
        $ebirrAccount = GlAccount::where('code', '1102')->first();
        $coopayAccount = GlAccount::where('code', '1103')->first();

        if (!$distributorAccount || !$cbeAccount || !$ebirrAccount || !$coopayAccount) {
            $this->command->error('Required GL accounts not found. Please run ChartOfAccountsSeeder first.');
            return;
        }

        // Define opening balances
        $bankBalances = [
            '1101' => 2000000.00, // CBE: 2M ETB
            '1102' => 1500000.00, // EBIRR: 1.5M ETB
            '1103' => 1000000.00, // COOPAY: 1M ETB
        ];
        $totalBankBalance = array_sum($bankBalances);

        $openingBalances = [
            'distributor' => $totalBankBalance, // Match total bank balances
            'banks' => $bankBalances,
        ];

        // Calculate total bank balances
        $totalBankBalance = array_sum($openingBalances['banks']);

        // Create opening balance journal
        $journal = GlJournal::create([
            'journal_no' => 'OPEN-' . Carbon::now()->format('Ymd-His'),
            'journal_date' => Carbon::now()->format('Y-m-d'),
            'currency' => 'ETB',
            'fx_rate' => 1.0,
            'source' => 'OPENING',
            'reference' => null,
            'memo' => 'Opening Balance Entry - ' . Carbon::now()->format('M Y'),
            'status' => 'POSTED',
            'posted_at' => Carbon::now(),
        ]);

        // Create journal lines
        $lineNumber = 1;

        // Debit bank accounts
        foreach ($openingBalances['banks'] as $code => $balance) {
            $account = GlAccount::where('code', $code)->first();
            if ($account && $balance > 0) {
                GlLine::create([
                    'journal_id' => $journal->id,
                    'line_no' => $lineNumber++,
                    'account_id' => $account->id,
                    'debit' => $balance,
                    'credit' => 0,
                    'memo' => "Opening balance for {$account->name}",
                ]);
            }
        }

        // Credit distributor account
        GlLine::create([
            'journal_id' => $journal->id,
            'line_no' => $lineNumber++,
            'account_id' => $distributorAccount->id,
            'debit' => 0,
            'credit' => $openingBalances['distributor'],
            'memo' => "Opening balance for {$distributorAccount->name}",
        ]);

        // Verify journal balances
        $totalDebits = $journal->lines()->sum('debit');
        $totalCredits = $journal->lines()->sum('credit');

        if ($totalDebits !== $totalCredits) {
            $this->command->warn("Journal {$journal->id} is unbalanced: Debits: {$totalDebits}, Credits: {$totalCredits}");
        } else {
            $this->command->info("Opening balance journal created successfully with balanced entries.");
        }

        // Update bank account statuses if needed
        $bankAccounts = BankAccount::all();
        foreach ($bankAccounts as $bankAccount) {
            $bankAccount->update(['is_active' => true]);
        }

        $this->command->info('Opening balances seeded successfully.');
    }
}