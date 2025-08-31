<?php

namespace App\Http\Requests\Telebirr;

use App\Models\TelebirrAgent;
use App\Models\BankAccount;
use Illuminate\Validation\Rule;

class PostRepayRequest extends PostTransactionRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'agent_short_code' => [
                'required',
                'string',
                'max:50',
                Rule::exists('telebirr_agents', 'short_code')
            ],
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
            'agent_short_code.required' => 'Agent short code is required for repayment',
            'agent_short_code.exists' => 'Agent not found',
            'bank_external_number.required' => 'Bank external number is required for repayment',
            'bank_external_number.exists' => 'Bank account not found',
            'payment_method.required' => 'Payment method is required for repayment',
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

            // Additional REPAY-specific validations
            if ($this->has('agent_short_code')) {
                $agent = TelebirrAgent::where('short_code', $this->agent_short_code)->first();

                if ($agent && !in_array($agent->status, ['Active', 'Dormant'])) {
                    $validator->errors()->add('agent_short_code', 'Agent must be in Active or Dormant status for repayment transactions');
                }
            }

            // Validate bank account is active
            if ($this->has('bank_external_number')) {
                $bankAccount = BankAccount::where('external_number', $this->bank_external_number)->first();

                if ($bankAccount && !$bankAccount->is_active) {
                    $validator->errors()->add('bank_external_number', 'Bank account is not active');
                }
            }
        });
    }
}