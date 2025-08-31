<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HomeController;

Route::get('/', [HomeController::class, 'index']);

Route::get('/login', function () {
    return response()->json(['message' => 'Please authenticate via API'], 401);
})->name('login');
