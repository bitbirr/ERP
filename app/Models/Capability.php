<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Capability extends Model
{
    use HasUuids;

    protected $fillable = ['name','key','group'];
    /**
     * The roles that have this capability.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_capability');
    }

    /**
     * The user policies for this capability.
     */
    public function userPolicies()
    {
        return $this->hasMany(UserPolicy::class, 'capability_key', 'key');
    }
}
