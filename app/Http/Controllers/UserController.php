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