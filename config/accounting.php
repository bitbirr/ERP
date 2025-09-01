<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for the accounting system. This should be a valid
    | ISO 4217 currency code (e.g., USD, EUR, ETB for Ethiopian Birr).
    |
    */

    'base_currency' => env('ACCOUNTING_BASE_CURRENCY', 'ETB'),

    /*
    |--------------------------------------------------------------------------
    | Currency Precision
    |--------------------------------------------------------------------------
    |
    | The number of decimal places to use for monetary amounts in the GL.
    | Common values are 2 (for cents) or 4 (for more precision).
    | This affects the database column precision for amounts.
    |
    */

    'precision' => env('ACCOUNTING_PRECISION', 2),

    /*
    |--------------------------------------------------------------------------
    | Scale
    |--------------------------------------------------------------------------
    |
    | The scale (decimal places) for numeric database columns.
    | This is used for defining NUMERIC(precision, scale) columns.
    |
    */

    'scale' => env('ACCOUNTING_SCALE', 2),

    /*
    |--------------------------------------------------------------------------
    | Journal Sources
    |--------------------------------------------------------------------------
    |
    | Predefined journal sources for categorizing transactions.
    | These can be extended as needed for different modules.
    |
    */

    'journal_sources' => [
        'POS' => 'Point of Sale',
        'INVENTORY' => 'Inventory Management',
        'MANUAL' => 'Manual Entry',
        'PAYROLL' => 'Payroll',
        'BANKING' => 'Banking',
        'ADJUSTMENT' => 'Adjustment',
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Types
    |--------------------------------------------------------------------------
    |
    | Standard account types for double-entry accounting.
    |
    */

    'account_types' => [
        'ASSET' => 'Asset',
        'LIABILITY' => 'Liability',
        'EQUITY' => 'Equity',
        'REVENUE' => 'Revenue',
        'EXPENSE' => 'Expense',
        'CONTRA_ASSET' => 'Contra Asset',
        'CONTRA_LIABILITY' => 'Contra Liability',
        'CONTRA_EQUITY' => 'Contra Equity',
        'CONTRA_REVENUE' => 'Contra Revenue',
        'CONTRA_EXPENSE' => 'Contra Expense',
    ],

    /*
    |--------------------------------------------------------------------------
    | Normal Balance Types
    |--------------------------------------------------------------------------
    |
    | The normal balance for each account type (DEBIT or CREDIT).
    |
    */

    'normal_balances' => [
        'ASSET' => 'DEBIT',
        'LIABILITY' => 'CREDIT',
        'EQUITY' => 'CREDIT',
        'REVENUE' => 'CREDIT',
        'EXPENSE' => 'DEBIT',
        'CONTRA_ASSET' => 'CREDIT',
        'CONTRA_LIABILITY' => 'DEBIT',
        'CONTRA_EQUITY' => 'DEBIT',
        'CONTRA_REVENUE' => 'DEBIT',
        'CONTRA_EXPENSE' => 'CREDIT',
    ],

    /*
    |--------------------------------------------------------------------------
    | Journal Statuses
    |--------------------------------------------------------------------------
    |
    | Valid statuses for GL journals.
    |
    */

    'journal_statuses' => [
        'DRAFT' => 'Draft',
        'POSTED' => 'Posted',
        'VOIDED' => 'Voided',
        'REVERSED' => 'Reversed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency Settings
    |--------------------------------------------------------------------------
    |
    | Settings for idempotency key handling.
    |
    */

    'idempotency' => [
        'lock_timeout_seconds' => env('ACCOUNTING_IDEMPOTENCY_LOCK_TIMEOUT', 300), // 5 minutes
        'max_retries' => env('ACCOUNTING_IDEMPOTENCY_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | POS Posting Rules
    |--------------------------------------------------------------------------
    |
    | Define GL account mappings for POS transactions.
    | Set 'enabled' to false to disable GL posting for POS.
    |
    */

    'pos_posting' => [
        'enabled' => env('POS_GL_POSTING_ENABLED', true),
        'rules' => [
            'sales_revenue' => env('POS_SALES_REVENUE_ACCOUNT', '4000'), // Revenue account
            'cash_receipt' => env('POS_CASH_ACCOUNT', '1001'), // Cash/Bank account
            'tax_payable' => env('POS_TAX_PAYABLE_ACCOUNT', '2001'), // Tax payable account
            'discount_expense' => env('POS_DISCOUNT_ACCOUNT', '5001'), // Discount expense account
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Settings
    |--------------------------------------------------------------------------
    |
    | Settings for financial reporting and views.
    |
    */

    'reporting' => [
        'use_materialized_views' => env('ACCOUNTING_USE_MATERIALIZED_VIEWS', false),
        'refresh_schedule' => env('ACCOUNTING_REFRESH_SCHEDULE', 'daily'),
    ],

];