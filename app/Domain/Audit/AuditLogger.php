<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditLogger
{
    /**
     * List of sensitive fields to mask in audit logs.
     */
    protected array $sensitiveFields = [
        'password', 'token', 'access_token', 'refresh_token', 'secret', 'ssn', 'email', 'phone', 'api_key', 'private_key',
    ];

    /**
     * Recursively mask sensitive fields in the given array.
     */
    protected function maskSensitiveData(?array $data): ?array
    {
        if (!is_array($data)) {
            return $data;
        }
        $masked = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = $this->maskSensitiveData($value);
            } elseif (in_array(strtolower($key), $this->sensitiveFields, true)) {
                $masked[$key] = '***';
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }

    public function log(string $action, ?Model $subject = null, array $old = null, array $new = null, array $context = []): void
    {
        try {
            $req = request();
            $user = optional($req)->user();

            AuditLog::create([
                'actor_id'         => $user?->getKey(),
                'actor_ip'         => $req?->ip(),
                'actor_user_agent' => $req?->userAgent(),
                'action'           => $action,
                'subject_type'     => $subject ? $subject->getMorphClass() : null,
                'subject_id'       => $subject ? (string) $subject->getKey() : null,
                'changes_old'      => $this->maskSensitiveData($old),
                'changes_new'      => $this->maskSensitiveData($new),
                'context'          => array_filter([
                    'route'       => $req?->route()?->getName(),
                    'uri'         => $req?->path(),
                    'http_method' => $req?->method(),
                    'branch_id'   => $req?->header('X-Branch-Id'),
                    'request_id'  => $req?->attributes->get('request_id'),
                ]),
                'created_at'       => now(),
            ]);
        } catch (\Exception $e) {
            // Log the audit error but don't fail the request
            \Log::error('Audit logging failed', [
                'error' => $e->getMessage(),
                'action' => $action,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
