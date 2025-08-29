<?php

namespace App\Console\Commands;

use App\Domain\Auth\RbacCacheBuilder;
use App\Models\User;
use Illuminate\Console\Command;

class RbacRebuild extends Command
{
    protected $signature = 'rbac:rebuild {--user=} {--all}';
    protected $description = 'Rebuild user_policies cache for RBAC';

    public function handle(RbacCacheBuilder $builder): int
    {
        if ($this->option('user')) {
            $user = User::find($this->option('user'));
            if (! $user) {
                $this->error('User not found.');
                return self::FAILURE;
            }
            $builder->rebuildForUser($user);
            $this->info("Rebuilt policies for user {$user->id}");
            return self::SUCCESS;
        }

        if ($this->option('all')) {
            User::query()->select('id')->chunk(500, function ($users) use ($builder) {
                foreach ($users as $u) {
                    $builder->rebuildForUser($u);
                }
            });
            $this->info('Rebuilt policies for all users.');
            return self::SUCCESS;
        }

        $this->warn('Provide --user=<id> or --all');
        return self::INVALID;
    }
}
