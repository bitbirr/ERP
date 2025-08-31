<?php

namespace App\Http\Requests\Telebirr;

use App\Models\TelebirrAgent;
use Illuminate\Validation\Rule;

class PostIssueRequest extends PostTransactionRequest
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
            'payment_method' => 'required|string|in:CASH,BANK_TRANSFER,MOBILE',
            'remarks' => 'required|string|max:1000',
        ]);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'agent_short_code.required' => 'Agent short code is required for issue transaction',
            'agent_short_code.exists' => 'Agent not found',
            'payment_method.required' => 'Payment method is required for issue transaction',
            'payment_method.in' => 'Payment method must be CASH, BANK_TRANSFER, or MOBILE',
            'remarks.required' => 'Remarks are required for issue transaction',
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

            // Additional ISSUE-specific validations
            if ($this->has('agent_short_code')) {
                $agent = TelebirrAgent::where('short_code', $this->agent_short_code)->first();

                if ($agent && !in_array($agent->status, ['Active', 'Dormant'])) {
                    $validator->errors()->add('agent_short_code', 'Agent must be in Active or Dormant status for issue transactions');
                }
            }
        });
    }
}