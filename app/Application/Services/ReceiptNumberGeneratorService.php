<?php

namespace App\Application\Services;

use Illuminate\Support\Str;

class ReceiptNumberGeneratorService
{
    /**
     * Generate a unique receipt number.
     *
     * @return string
     */
    public function generate()
    {
        // Example logic for generating a unique receipt number
        return 'REC-' . strtoupper(Str::random(8));
    }
}