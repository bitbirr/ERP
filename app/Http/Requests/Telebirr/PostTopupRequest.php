<?php

namespace App\Http\Requests\Telebirr;

class PostTopupRequest extends PostTransactionRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'bank_external_number' => 'required|string|max:50|exists:bank_accounts,external_number',
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
        ]);
    }
}