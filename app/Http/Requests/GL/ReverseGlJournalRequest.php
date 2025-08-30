<?php

namespace App\Http\Requests\GL;

use Illuminate\Foundation\Http\FormRequest;

class ReverseGlJournalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('gl.reverse');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:500',
                'min:5',
            ],
            'reversal_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A reason for reversal is required.',
            'reason.min' => 'The reversal reason must be at least 5 characters.',
            'reason.max' => 'The reversal reason must not exceed 500 characters.',
            'reversal_date.after_or_equal' => 'Reversal date cannot be in the past.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'reason' => 'reversal reason',
            'reversal_date' => 'reversal date',
        ];
    }
}