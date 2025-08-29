<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Role extends Model
{
    use HasUuids;

    protected $fillable = ['name','slug','is_system'];

    public function capabilities()
    {
        return $this->belongsToMany(Capability::class, 'role_capability');
    }
    /**
     * Get the assignments for the role.
     */
    public function assignments()
    {
        return $this->hasMany(UserRoleAssignment::class);
    }
}
