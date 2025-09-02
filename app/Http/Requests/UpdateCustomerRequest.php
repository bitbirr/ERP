<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasCapability('manage_customers');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::in(['individual', 'organization'])],
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'nullable', 'email', Rule::unique('customers')->ignore($this->route('customer')->id)],
            'phone' => 'sometimes|nullable|string|max:20',
            'tax_id' => 'sometimes|nullable|string|max:50',
            'description' => 'sometimes|nullable|string|max:1000',
            'is_active' => 'boolean',
            'metadata' => 'sometimes|nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Customer type is required.',
            'type.in' => 'Customer type must be either individual or organization.',
            'name.required' => 'Customer name is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already in use.',
        ];
    }
}