<?php

namespace App\Http\Requests\Telebirr;

use Illuminate\Foundation\Http\FormRequest;

class PostTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('telebirr.post');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'currency' => 'nullable|string|size:3|in:ETB,USD,EUR',
            'idempotency_key' => 'required|string|max:255|unique:telebirr_transactions,idempotency_key',
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
}