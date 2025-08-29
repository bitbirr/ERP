<?php

use App\Console\Commands\RbacRebuild;
use App\Domain\Auth\RbacCacheBuilder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('rbac:rebuild with --user calls builder', function () {
    $user = User::factory()->create();

    $mock = Mockery::mock(RbacCacheBuilder::class);
    $mock->shouldReceive('rebuildForUser')->once()->withArgs(function ($arg) use ($user) {
        return $arg->id === $user->id;
    });
    $this->app->instance(RbacCacheBuilder::class, $mock);

    Artisan::call('rbac:rebuild', ['--user' => $user->id]);
    expect(Artisan::output())->toContain('Rebuilt policies for user');
});
