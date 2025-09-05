<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Auth\RbacCacheBuilder;
use App\Models\User;

$users = User::where('email', 'like', '%@najib.shop')->get();
foreach ($users as $user) {
    echo "Processing " . $user->email . "\n";

    $assignments = \App\Models\UserRoleAssignment::where('user_id', $user->id)->with('role.capabilities')->get();
    echo "Assignments: " . $assignments->count() . "\n";
    if ($assignments->isEmpty()) {
        echo "No assignments\n";
        continue;
    }

    foreach ($assignments as $a) {
        echo "Role: " . $a->role->slug . ", Caps: " . $a->role->capabilities->count() . "\n";
    }

    // Clear existing
    \App\Models\UserPolicy::where('user_id', $user->id)->delete();

    // Insert manually
    $caps = $assignments->flatMap(fn ($a) => $a->role->capabilities->pluck('key'))->unique()->values();
    echo "Inserting " . $caps->count() . " capabilities\n";
    foreach ($caps as $key) {
        \App\Models\UserPolicy::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'branch_id' => null, // For simplicity, set to null
            'capability_key' => $key,
            'granted' => true,
        ]);
    }
}
echo "Rebuilt cache for najib users\n";