<?php

namespace App\Http\Requests\Telebirr;

use App\Models\TelebirrAgent;

class PostLoanRequest extends PostIssueRequest
{
    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Call parent validations
            parent::withValidator($validator);

            // Additional LOAN-specific validations
            if ($this->has('agent_short_code')) {
                $agent = TelebirrAgent::where('short_code', $this->agent_short_code)->first();

                if ($agent) {
                    // For LOAN transactions, allow negative AR (outstanding balance)
                    // This is different from ISSUE which might have stricter balance requirements
                    $outstandingBalance = $agent->getOutstandingBalance();

                    // LOAN can allow negative AR as per business rules
                    // No additional balance validation needed for LOAN
                }
            }
        });
    }
}