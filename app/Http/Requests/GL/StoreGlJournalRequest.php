<?php

namespace App\Http\Requests\GL;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGlJournalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('gl.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'journal_no' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('gl_journals', 'journal_no'),
            ],
            'journal_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
            'fx_rate' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.999999',
            ],
            'source' => [
                'nullable',
                'string',
                Rule::in(array_keys(config('accounting.journal_sources', []))),
            ],
            'reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'memo' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'branch_id' => [
                'nullable',
                'uuid',
                Rule::exists('branches', 'id'),
            ],
            'lines' => [
                'required',
                'array',
                'min:2',
            ],
            'lines.*.account_id' => [
                'required',
                'uuid',
                Rule::exists('gl_accounts', 'id')->where(function ($query) {
                    $query->where('is_postable', true)
                          ->where('status', 'ACTIVE');
                }),
            ],
            'lines.*.branch_id' => [
                'nullable',
                'uuid',
                Rule::exists('branches', 'id'),
            ],
            'lines.*.cost_center_id' => [
                'nullable',
                'uuid',
                // Rule::exists('cost_centers', 'id'), // Uncomment when cost_centers table exists
            ],
            'lines.*.project_id' => [
                'nullable',
                'uuid',
                // Rule::exists('projects', 'id'), // Uncomment when projects table exists
            ],
            'lines.*.customer_id' => [
                'nullable',
                'uuid',
                // Rule::exists('customers', 'id'), // Uncomment when customers table exists
            ],
            'lines.*.supplier_id' => [
                'nullable',
                'uuid',
                // Rule::exists('suppliers', 'id'), // Uncomment when suppliers table exists
            ],
            'lines.*.item_id' => [
                'nullable',
                'uuid',
                Rule::exists('inventory_items', 'id'),
            ],
            'lines.*.memo' => [
                'nullable',
                'string',
                'max:500',
            ],
            'lines.*.debit' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
                'decimal:0,2',
            ],
            'lines.*.credit' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
                'decimal:0,2',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'journal_date.before_or_equal' => 'Journal date cannot be in the future.',
            'currency.regex' => 'Currency must be a valid 3-letter ISO code.',
            'lines.min' => 'A journal must have at least 2 lines.',
            'lines.*.account_id.exists' => 'The selected account is not valid or not postable.',
            'lines.*.debit.decimal' => 'Debit amount must have at most 2 decimal places.',
            'lines.*.credit.decimal' => 'Credit amount must have at most 2 decimal places.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'lines.*.account_id' => 'account',
            'lines.*.debit' => 'debit amount',
            'lines.*.credit' => 'credit amount',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lines = $this->input('lines', []);

            // Check that journal balances (debits = credits)
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($lines as $index => $line) {
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                // Check that each line has only one side
                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add("lines.{$index}.debit", 'A line cannot have both debit and credit amounts.');
                    $validator->errors()->add("lines.{$index}.credit", 'A line cannot have both debit and credit amounts.');
                }

                // Check that each line has at least one side
                if ($debit == 0 && $credit == 0) {
                    $validator->errors()->add("lines.{$index}.debit", 'A line must have either a debit or credit amount.');
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            // Check balance
            if (abs($totalDebit - $totalCredit) > 0.01) { // Allow for small floating point differences
                $validator->errors()->add('lines', "Journal debits ({$totalDebit}) must equal credits ({$totalCredit}).");
            }
        });
    }
}