<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Middleware\EnsureHasCapability;
use App\Models\User;
use App\Models\UserPolicy;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class RbacMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register test routes with different capabilities
        Route::middleware(['web', EnsureHasCapability::class . ':tx.view'])
            ->get('/_rbac_test_tx_view', fn() => response()->json(['ok' => true]));

        Route::middleware(['web', EnsureHasCapability::class . ':telebirr.topup.create'])
            ->get('/_rbac_test_telebirr_topup', fn() => response()->json(['ok' => true]));

        Route::middleware(['web', EnsureHasCapability::class . ':gl.post.create'])
            ->get('/_rbac_test_gl_post', fn() => response()->json(['ok' => true]));

        Route::middleware(['web', EnsureHasCapability::class . ':audit.read'])
            ->get('/_rbac_test_audit_read', fn() => response()->json(['ok' => true]));
    }

    /** @test */
    public function forbidden_when_missing_capability()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/_rbac_test_tx_view')->assertStatus(403);
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

        $this->actingAs($user)->get('/_rbac_test_tx_view')->assertOk();
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

        $this->actingAs($user)->get('/_rbac_test_tx_view')->assertStatus(403);
    }

    /** @test */
    public function allows_telebirr_topup_capability()
    {
        $user = User::factory()->create();
        UserPolicy::create([
            'user_id' => $user->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.topup.create',
            'granted' => true,
        ]);

        $this->actingAs($user)->get('/_rbac_test_telebirr_topup')->assertOk();
    }

    /** @test */
    public function allows_gl_post_capability()
    {
        $user = User::factory()->create();
        UserPolicy::create([
            'user_id' => $user->id,
            'branch_id' => null,
            'capability_key' => 'gl.post.create',
            'granted' => true,
        ]);

        $this->actingAs($user)->get('/_rbac_test_gl_post')->assertOk();
    }

    /** @test */
    public function allows_audit_read_capability()
    {
        $user = User::factory()->create();
        UserPolicy::create([
            'user_id' => $user->id,
            'branch_id' => null,
            'capability_key' => 'audit.read',
            'granted' => true,
        ]);

        $this->actingAs($user)->get('/_rbac_test_audit_read')->assertOk();
    }

    /** @test */
    public function branch_specific_capability_works()
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();

        // Grant capability for specific branch
        UserPolicy::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'capability_key' => 'tx.view',
            'granted' => true,
        ]);

        // Should work with branch header
        $this->actingAs($user)
            ->withHeader('X-Branch-Id', $branch->id)
            ->get('/_rbac_test_tx_view')
            ->assertOk();

        // Should fail without branch header (different branch context)
        $this->actingAs($user)->get('/_rbac_test_tx_view')->assertStatus(403);
    }

    /** @test */
    public function global_capability_works_without_branch()
    {
        $user = User::factory()->create();

        // Grant global capability (null branch_id)
        UserPolicy::create([
            'user_id' => $user->id,
            'branch_id' => null,
            'capability_key' => 'tx.view',
            'granted' => true,
        ]);

        // Should work without branch header
        $this->actingAs($user)->get('/_rbac_test_tx_view')->assertOk();
    }

    /** @test */
    public function unauthenticated_user_gets_401()
    {
        $this->get('/_rbac_test_tx_view')->assertStatus(401);
    }

    /** @test */
    public function middleware_logs_rbac_check()
    {
        $user = User::factory()->create();

        // This should trigger audit logging
        $this->actingAs($user)->get('/_rbac_test_tx_view')->assertStatus(403);

        // Note: In a real test, you might want to assert that audit logs were created
        // But for now, we're just ensuring the middleware runs without errors
    }
}
