<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Middleware\EnsureHasCapability;
use App\Models\User;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class RbacMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a test route with the middleware
        Route::middleware(['web', EnsureHasCapability::class . ':tx.view'])
            ->get('/_rbac_test', fn() => response()->json(['ok' => true]));
    }

    /** @test */
    public function forbidden_when_missing_capability()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/_rbac_test')->assertStatus(403);
    }

    /** @test */
    public function allowed_when_capability_granted()
    {
        $user = User::factory()->create();
        UserPolicy::create([
            'user_id' => $user->id,
            'branch_id' => null,
            'capability_key' => 'tx.view',
            'granted' => true,
        ]);

        $this->actingAs($user)->get('/_rbac_test')->assertOk();
    }

    /** @test */
    public function forbidden_when_capability_denied()
    {
        $user = User::factory()->create();
        UserPolicy::create([
            'user_id' => $user->id,
            'branch_id' => null,
            'capability_key' => 'tx.view',
            'granted' => false,
        ]);

        $this->actingAs($user)->get('/_rbac_test')->assertStatus(403);
    }
}
