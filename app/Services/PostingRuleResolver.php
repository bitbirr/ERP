<?php

namespace App\Services;

use App\Models\GlAccount;
use Illuminate\Support\Collection;

class PostingRuleResolver
{
    /**
     * Resolve posting rules for a transaction type
     */
    public function resolve(string $txType, array $context = []): array
    {
        $config = config('telebirr_postings.' . $txType);

        if (!$config) {
            throw new \Exception("No posting rules found for transaction type: {$txType}");
        }

        // Handle dynamic account resolution
        $resolved = $this->resolveDynamicAccounts($config, $context);

        return $resolved;
    }

    /**
     * Resolve dynamic accounts based on context
     */
    private function resolveDynamicAccounts(array $config, array $context): array
    {
        $resolved = $config;

        // For TOPUP and REPAY, the bank account is dynamic
        if (isset($context['bank_account_id'])) {
            $bankAccount = \App\Models\BankAccount::find($context['bank_account_id']);
            if ($bankAccount) {
                if (isset($resolved['debit_account']) && $resolved['debit_account'] === '1101') {
                    $resolved['debit_account'] = $bankAccount->glAccount->code;
                }
                if (isset($resolved['credit_account']) && $resolved['credit_account'] === '1101') {
                    $resolved['credit_account'] = $bankAccount->glAccount->code;
                }
            }
        }

        return $resolved;
    }

    /**
     * Get all available posting rules
     */
    public function getAllRules(): Collection
    {
        return collect(config('telebirr_postings'))
            ->filter(function ($value, $key) {
                return is_array($value) && isset($value['debit_account']);
            });
    }

    /**
     * Validate posting rules configuration
     */
    public function validateRules(): array
    {
        $errors = [];
        $rules = $this->getAllRules();

        foreach ($rules as $txType => $rule) {
            // Check if accounts exist
            if (isset($rule['debit_account'])) {
                $debitAccount = GlAccount::where('code', $rule['debit_account'])->first();
                if (!$debitAccount) {
                    $errors[] = "Debit account {$rule['debit_account']} not found for {$txType}";
                } elseif (!$debitAccount->is_postable) {
                    $errors[] = "Debit account {$rule['debit_account']} is not postable for {$txType}";
                }
            }

            if (isset($rule['credit_account'])) {
                $creditAccount = GlAccount::where('code', $rule['credit_account'])->first();
                if (!$creditAccount) {
                    $errors[] = "Credit account {$rule['credit_account']} not found for {$txType}";
                } elseif (!$creditAccount->is_postable) {
                    $errors[] = "Credit account {$rule['credit_account']} is not postable for {$txType}";
                }
            }
        }

        return $errors;
    }

    /**
     * Get subledger configuration
     */
    public function getSubledgerConfig(string $type): ?array
    {
        return config("telebirr_postings.subledger.{$type}");
    }

    /**
     * Generate subledger dimension value
     */
    public function generateSubledgerValue(string $type, array $context): string
    {
        $config = $this->getSubledgerConfig($type);

        if (!$config) {
            return '';
        }

        $format = $config['dimension_value_format'] ?? '';

        // Replace placeholders with context values
        foreach ($context as $key => $value) {
            $format = str_replace("{{$key}}", $value, $format);
        }

        return $format;
    }
}