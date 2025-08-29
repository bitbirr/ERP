<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserRoleAssignment extends Model
{
    use HasUuids;

    protected $fillable = ['user_id','role_id','branch_id'];

    public function role() { return $this->belongsTo(Role::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function user() { return $this->belongsTo(User::class); }
}