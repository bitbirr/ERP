<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserCollection;

class UserController extends Controller
{
    // GET /api/users
    public function index(Request $request)
    {
        $query = User::with('roleAssignments.role');

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Handle role filter
        if ($request->has('roles') && !empty($request->roles)) {
            $roles = is_array($request->roles) ? $request->roles : [$request->roles];
            $query->whereHas('roleAssignments.role', function ($q) use ($roles) {
                $q->whereIn('name', $roles);
            });
        }

        // Handle email verified filter
        if ($request->has('email_verified')) {
            $verified = $request->email_verified;
            if ($verified === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($verified === 'not_verified') {
                $query->whereNull('email_verified_at');
            }
        }

        // Handle sorting
        if ($request->has('sort_by')) {
            $sortBy = $request->sort_by;
            $sortOrder = $request->get('sort_order', 'asc');
            $allowedSorts = ['name', 'email', 'created_at', 'updated_at'];

            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Handle pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return new UserCollection($users);
    }

    // GET /api/users/{user}
    public function show(User $user)
    {
        $user->load('roleAssignments.role');
        return new UserResource($user);
    }

    // POST /api/users
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => $data['email_verified_at'] ?? now(),
        ]);

        $user->load('roleAssignments.role');
        return new UserResource($user);
    }

    // PATCH /api/users/{user}
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        $user->load('roleAssignments.role');

        return new UserResource($user);
    }

    // DELETE /api/users/{user}
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}