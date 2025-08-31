<?php

namespace App\Http\Requests\Telebirr;

use App\Models\TelebirrAgent;
use App\Models\BankAccount;
use App\Models\TelebirrTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by middleware, skip Gate check to avoid conflicts
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'currency' => 'nullable|string|size:3|in:ETB,USD,EUR',
            'idempotency_key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('telebirr_transactions', 'idempotency_key')
            ],
            'external_ref' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Transaction amount is required',
            'amount.numeric' => 'Amount must be a valid number',
            'amount.min' => 'Amount must be greater than zero',
            'amount.max' => 'Amount exceeds maximum allowed value',
            'currency.size' => 'Currency code must be exactly 3 characters',
            'currency.in' => 'Currency must be ETB, USD, or EUR',
            'idempotency_key.required' => 'Idempotency key is required',
            'idempotency_key.unique' => 'This transaction has already been processed',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Enhanced idempotency key validation
            if ($this->has('idempotency_key')) {
                $idempotencyService = app(\App\Application\Services\IdempotencyKeyService::class);

                // Check if key already exists in database
                $existing = $idempotencyService->getExistingTransaction($this->idempotency_key);
                if ($existing) {
                    $validator->errors()->add('idempotency_key', 'This transaction has already been processed');
                    return; // Return the existing transaction result
                }

                // Validate with service (includes cache check)
                $route = $this->path(); // Get current route
                if (!$idempotencyService->validate($this->idempotency_key, $route, $this->all())) {
                    $validator->errors()->add('idempotency_key', 'Idempotency key validation failed');
                }
            }

            // Additional validations based on transaction type
            $this->validateTransactionSpecificRules($validator);
        });
    }

    /**
     * Validate transaction-specific rules
     */
    protected function validateTransactionSpecificRules($validator)
    {
        // Agent validation for ISSUE, REPAY, LOAN
        if ($this->has('agent_short_code')) {
            $agent = TelebirrAgent::where('short_code', $this->agent_short_code)->first();

            if (!$agent) {
                $validator->errors()->add('agent_short_code', 'Agent not found');
                return;
            }

            // Check agent status
            if (!in_array($agent->status, ['Active', 'Dormant'])) {
                $validator->errors()->add('agent_short_code', 'Agent is not in an active or dormant state');
            }
        }

        // Bank account validation for TOPUP, REPAY
        if ($this->has('bank_external_number')) {
            $bankAccount = BankAccount::where('external_number', $this->bank_external_number)
                ->where('is_active', true)
                ->first();

            if (!$bankAccount) {
                $validator->errors()->add('bank_external_number', 'Bank account not found or inactive');
                return;
            }

            // For TOPUP, check distributor balance
            if ($this->isMethod('post') && str_contains($this->path(), 'topup')) {
                $this->validateTopupBalance($validator, $bankAccount);
            }
        }
    }

    /**
     * Validate balance for TOPUP transactions
     */
    protected function validateTopupBalance($validator, BankAccount $bankAccount)
    {
        // This would typically check the GL balance for the distributor account
        // For now, we'll add a placeholder validation
        // In a real implementation, you'd query the GL system for available balance

        // Placeholder: Assume distributor balance check
        // $distributorBalance = $this->getDistributorBalance();
        // if ($distributorBalance < $this->amount) {
        //     $validator->errors()->add('amount', 'Insufficient distributor balance for topup');
        // }
    }

    /**
     * Get distributor balance (placeholder implementation)
     */
    protected function getDistributorBalance(): float
    {
        // This should query the GL system for distributor account balance
        // For now, return a large number to pass validation
        return 999999999.99;
    }
}