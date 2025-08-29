<?php

namespace App\Traits;

use App\Domain\Auth\RbacCacheBuilder;
use App\Models\Branch;
use App\Models\UserPolicy;

trait HasCapabilities
{
    public function hasCapability(string $key, ?Branch $branch = null): bool
    {
        $query = UserPolicy::query()
            ->where('user_id', $this->getKey())
            ->where('capability_key', $key)
            ->where('granted', true);

        if ($branch) {
            $query->where('branch_id', $branch->getKey())->orWhereNull('branch_id');
        }

        $has = $query->exists();

        if (! $has) {
            // Attempt to rebuild cache once if empty/missing
            app(RbacCacheBuilder::class)->rebuildForUser($this);
            $has = $query->exists();
        }

        return $has;
    }
}
