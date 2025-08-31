<?php

namespace App\Http\Requests\Telebirr;

class PostIssueRequest extends PostTransactionRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'agent_short_code' => 'required|string|max:50|exists:telebirr_agents,short_code',
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
            'remarks.required' => 'Remarks are required for issue transaction',
        ]);
    }
}