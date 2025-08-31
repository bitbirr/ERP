<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telebirr Posting Rules
    |--------------------------------------------------------------------------
    |
    | Define the GL account mappings for different transaction types.
    | These can be modified by finance without code changes.
    |
    */

    'TOPUP' => [
        'debit_account' => '1101', // Bank account (will be resolved dynamically)
        'credit_account' => '1200', // Telebirr Distributor
        'description' => 'Buy e-float from HO',
    ],

    'ISSUE' => [
        'debit_account' => '1200', // Telebirr Distributor
        'credit_account' => '1300', // AR - Agents
        'description' => 'Give e-float to agent',
    ],

    'REPAY' => [
        'debit_account' => '1300', // AR - Agents
        'credit_account' => '1101', // Bank account (will be resolved dynamically)
        'description' => 'Agent pays back / settles',
    ],

    'LOAN' => [
        'debit_account' => '1200', // Telebirr Distributor
        'credit_account' => '1300', // AR - Agents
        'description' => 'E-float now, pay later',
    ],

    /*
    |--------------------------------------------------------------------------
    | EBIRR Clearing (Optional)
    |--------------------------------------------------------------------------
    |
    | If using EBIRR clearing flow, uncomment and configure:
    |
    */
    // 'EBIRR_CLEARING' => [
    //     'clearing_account' => '1312', // AR - EBIRR
    //     'settlement_bank' => '1102', // Bank - EBIRR
    // ],

    /*
    |--------------------------------------------------------------------------
    | Subledger Configuration
    |--------------------------------------------------------------------------
    |
    | Define how subledger dimensions are applied
    |
    */
    'subledger' => [
        'agents' => [
            'account_code' => '1300',
            'dimension_key' => 'Agent',
            'dimension_value_format' => 'SC{short_code}',
        ],
    ],
];