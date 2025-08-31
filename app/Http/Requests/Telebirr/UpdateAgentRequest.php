<?php

namespace App\Http\Requests\Telebirr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
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
        $agentId = $this->route('agent')->id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'short_code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('telebirr_agents', 'short_code')->ignore($agentId),
            ],
            'phone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'location' => 'nullable|string|max:255',
            'status' => 'sometimes|required|in:Active,Inactive',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Agent name is required',
            'short_code.required' => 'Agent short code is required',
            'short_code.unique' => 'This short code is already in use',
            'phone.regex' => 'Phone number format is invalid',
            'status.in' => 'Status must be either Active or Inactive',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'short_code' => 'agent short code',
        ];
    }
}