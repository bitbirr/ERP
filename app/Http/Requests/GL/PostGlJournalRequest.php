<?php

namespace App\Http\Requests\GL;

use Illuminate\Foundation\Http\FormRequest;

class PostGlJournalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('gl.post');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'idempotency_key.max' => 'Idempotency key must not exceed 255 characters.',
        ];
    }
}