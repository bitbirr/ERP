<?php

namespace App\Domain\Auth;

use App\Models\Capability;
use App\Models\User;
use App\Models\UserPolicy;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use App\Domain\Audit\AuditLogger;

class RbacCacheBuilder
{
    public function rebuildForUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            UserPolicy::where('user_id', $user->getKey())->delete();

            // All assignments for user (global + per-branch)
            $assignments = UserRoleAssignment::with('role.capabilities')
                ->where('user_id', $user->getKey())
                ->get();

            // Group by branch_id (including null)
            $byBranch = $assignments->groupBy('branch_id');

            $rows = [];

            foreach ($byBranch as $branchId => $items) {
                /** @var Collection<int,\App\Models\UserRoleAssignment> $items */
                $caps = $items->flatMap(fn ($a) => $a->role->capabilities->pluck('key'))
                              ->unique()
                              ->values();

                foreach ($caps as $key) {
                    $rows[] = [
                        'id'             => DB::raw('gen_random_uuid()'),
                        'user_id'        => $user->getKey(),
                        'branch_id'      => $branchId ?: null, // Ensure null instead of empty string
                        'capability_key' => $key,
                        'granted'        => true,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }
            }

            if (! empty($rows)) {
                foreach (array_chunk($rows, 1000) as $chunk) {
                    UserPolicy::insert($chunk);
                }
            }

            // Audit log for cache rebuild
            app(AuditLogger::class)->log(
                'rbac.cache_rebuild',
                $user,
                null,
                null,
                [
                    'user_id' => $user->getKey(),
                    'branch_ids' => $byBranch->keys()->all(),
                    'capability_count' => count($rows),
                ]
            );
        });
    }

    public static function rebuildAll(): void
    {
        $users = User::all();
        foreach ($users as $user) {
            app(self::class)->rebuildForUser($user);
        }
    }
}
