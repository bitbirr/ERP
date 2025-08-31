<?php

namespace App\Http\Requests\Telebirr;

class PostRepayRequest extends PostTransactionRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'agent_short_code' => 'required|string|max:50|exists:telebirr_agents,short_code',
            'bank_external_number' => 'required|string|max:50|exists:bank_accounts,external_number',
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
        ]);
    }
}