<?php

use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creating a role writes an audit log', function () {
    Role::create(['name' => 'Manager','slug' => 'manager','is_system' => false]);
    expect(AuditLog::count())->toBe(1);
    $log = AuditLog::first();
    expect($log->action)->toBe('role.created');
});
