<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Capability;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Models\Branch;
use App\Domain\Auth\RbacCacheBuilder;

class RbacController extends Controller
{
    // POST /api/rbac/roles
    public function createRole(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'slug' => 'required|string|unique:roles,slug',
            'is_system' => 'boolean',
        ]);
        $role = Role::create($data);
        return response()->json($role, 201);
    }

    // PATCH /api/rbac/roles/{id}
    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $data = $request->validate([
            'name' => 'string',
            'slug' => 'string|unique:roles,slug,' . $role->id,
            'is_system' => 'boolean',
        ]);
        $role->update($data);
        return response()->json($role);
    }

    // POST /api/rbac/roles/{id}/capabilities
    public function syncRoleCapabilities(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $data = $request->validate([
            'capability_keys' => 'required|array',
            'capability_keys.*' => 'string|exists:capabilities,key',
        ]);
        $capIds = Capability::whereIn('key', $data['capability_keys'])->pluck('id')->all();
        $role->capabilities()->sync($capIds);
        return response()->json(['message' => 'Capabilities synced']);
    }

    // POST /api/rbac/users/{user}/roles
    public function assignUserRole(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $data = $request->validate([
            'role_slug' => 'required|string|exists:roles,slug',
            'branch_code' => 'nullable|string|exists:branches,code',
        ]);
        $role = Role::where('slug', $data['role_slug'])->first();
        $branchId = null;
        if (!empty($data['branch_code'])) {
            $branch = Branch::where('code', $data['branch_code'])->first();
            $branchId = $branch ? $branch->id : null;
        }
        $assignment = UserRoleAssignment::firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'branch_id' => $branchId,
        ]);
        return response()->json($assignment, 201);
    }

    // POST /api/rbac/rebuild
    public function rebuildRbacCache(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);
        $userId = $request->input('user_id');
        if ($userId) {
            RbacCacheBuilder::rebuildForUser($userId);
        } else {
            RbacCacheBuilder::rebuildAll();
        }
        return response()->json(['message' => 'RBAC cache rebuilt']);
    }
}