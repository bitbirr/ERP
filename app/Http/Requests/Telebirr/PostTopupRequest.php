<?php

namespace App\Http\Requests\Telebirr;

use App\Models\BankAccount;
use Illuminate\Validation\Rule;

class PostTopupRequest extends PostTransactionRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'bank_external_number' => [
                'required',
                'string',
                'max:50',
                Rule::exists('bank_accounts', 'external_number')
            ],
            'payment_method' => 'required|string|in:CASH,BANK_TRANSFER,MOBILE',
        ]);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'bank_external_number.required' => 'Bank external number is required for topup',
            'bank_external_number.exists' => 'Bank account not found',
            'payment_method.required' => 'Payment method is required for topup',
            'payment_method.in' => 'Payment method must be CASH, BANK_TRANSFER, or MOBILE',
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Call parent validations
            parent::withValidator($validator);

            // Additional TOPUP-specific validations
            if ($this->has('bank_external_number')) {
                $bankAccount = BankAccount::where('external_number', $this->bank_external_number)->first();

                if ($bankAccount && !$bankAccount->is_active) {
                    $validator->errors()->add('bank_external_number', 'Bank account is not active');
                }

                // Check distributor balance for TOPUP
                $this->validateDistributorBalance($validator);
            }
        });
    }

    /**
     * Validate distributor balance for TOPUP
     */
    protected function validateDistributorBalance($validator)
    {
        // In a real implementation, this would check the GL balance
        // For TOPUP transactions, distributor must have sufficient balance
        $distributorBalance = $this->getDistributorBalance();

        if ($distributorBalance < $this->amount) {
            $validator->errors()->add('amount', 'Insufficient distributor balance for topup transaction');
        }
    }

    /**
     * Get distributor balance (placeholder - should query GL system)
     */
    protected function getDistributorBalance(): float
    {
        // This should query the GL system for distributor account balance
        // For now, return a large number to simulate sufficient balance
        // In production, this would be something like:
        // return app(GlService::class)->getAccountBalance('1200'); // Distributor account
        return 999999999.99;
    }
}