<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $fillable = [
        'actor_id','actor_ip','actor_user_agent','action',
        'subject_type','subject_id','changes_old','changes_new','context','created_at'
    ];

    protected $casts = [
        'changes_old' => 'array',
        'changes_new' => 'array',
        'context'     => 'array',
        'created_at'  => 'datetime',
    ];
}
