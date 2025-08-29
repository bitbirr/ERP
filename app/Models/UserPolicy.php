<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserPolicy extends Model
{
    use HasUuids;

    protected $fillable = ['user_id','branch_id','capability_key','granted'];
}
