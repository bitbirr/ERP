<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;

// API CSRF token route for Sanctum
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});

// Authentication routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Catch-all route to serve the React SPA
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
