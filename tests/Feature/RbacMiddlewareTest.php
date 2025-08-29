<?php

use App\Http\Middleware\EnsureHasCapability;
use App\Models\User;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', EnsureHasCapability::class . ':tx.view'])
        ->get('/_rbac_test', fn() => response()->json(['ok' => true]));
});

test('forbidden when missing capability', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/_rbac_test')->assertStatus(403);
});

test('allowed when capability cached', function () {
    $user = User::factory()->create();
    UserPolicy::create([
        'user_id' => $user->id,
        'branch_id' => null,
        'capability_key' => 'tx.view',
        'granted' => true,
    ]);

    $this->actingAs($user)->get('/_rbac_test')->assertOk();
});
