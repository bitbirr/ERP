<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'branch_id' => 'required|uuid|exists:branches,id',
            'customer_id' => 'nullable|uuid|exists:customers,id',
            'currency' => 'required|string|size:3',
            'notes' => 'nullable|string|max:1000',
            'line_items' => 'required|array|min:1',
            'line_items.*.product_id' => 'required|uuid|exists:products,id',
            'line_items.*.uom' => 'required|string|max:10',
            'line_items.*.qty' => 'required|numeric|min:0.01',
            'line_items.*.price' => 'required|numeric|min:0',
            'line_items.*.discount' => 'nullable|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_amount' => 'nullable|numeric|min:0',
            'line_items.*.notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'line_items.required' => 'At least one line item is required.',
            'line_items.*.product_id.required' => 'Product is required for each line item.',
            'line_items.*.qty.min' => 'Quantity must be greater than 0.',
        ];
    }
}