<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

class IdempotencyKey extends Model
{
    use HasUuids;

    protected $fillable = [
        'scope',
        'key',
        'request_hash',
        'status',
        'response_snapshot',
        'locked_until',
    ];

    protected $casts = [
        'response_snapshot' => 'array',
        'locked_until' => 'datetime',
    ];

    // Scopes
    public function scopeByScope($query, $scope)
    {
        return $query->where('scope', $scope);
    }

    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeSucceeded($query)
    {
        return $query->where('status', 'SUCCEEDED');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'FAILED');
    }

    public function scopeLocked($query)
    {
        return $query->where('locked_until', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('locked_until', '<=', now());
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isSucceeded(): bool
    {
        return $this->status === 'SUCCEEDED';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->locked_until && $this->locked_until->isPast();
    }

    public function lock(int $seconds = 300): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        $this->update([
            'status' => 'PENDING',
            'locked_until' => now()->addSeconds($seconds),
        ]);

        return true;
    }

    public function unlock(): bool
    {
        return $this->update(['locked_until' => null]);
    }

    public function markSucceeded($response = null): bool
    {
        return $this->update([
            'status' => 'SUCCEEDED',
            'response_snapshot' => $response,
            'locked_until' => null,
        ]);
    }

    public function markFailed($response = null): bool
    {
        return $this->update([
            'status' => 'FAILED',
            'response_snapshot' => $response,
            'locked_until' => null,
        ]);
    }

    // Static methods
    public static function findByScopeAndKey(string $scope, string $key): ?self
    {
        return static::where('scope', $scope)
            ->where('key', $key)
            ->first();
    }

    public static function acquireLock(string $scope, string $key, int $lockTimeout = 300): ?self
    {
        $keyRecord = static::firstOrCreate([
            'scope' => $scope,
            'key' => $key,
        ]);

        if ($keyRecord->lock($lockTimeout)) {
            return $keyRecord;
        }

        return null;
    }
}
