<?php

namespace App\Http\Requests\Telebirr;

class PostLoanRequest extends PostIssueRequest
{
    // Loan request inherits all validation from PostIssueRequest
    // since loan transactions have the same requirements as issue transactions
}