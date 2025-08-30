<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_number',
        'date',
        'description',
        'total_debit',
        'total_credit',
    ];

    public function lines()
    {
        return $this->hasMany(GlLine::class);
    }
}
