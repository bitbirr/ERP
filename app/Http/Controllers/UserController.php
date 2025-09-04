<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    // POST /api/users
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:12',
            'email_verified_at' => 'nullable|date',
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => $data['email_verified_at'] ?? now(),
        ]);

        return response()->json($user, 201);
    }
}