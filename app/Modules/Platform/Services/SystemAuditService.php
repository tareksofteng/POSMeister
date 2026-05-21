<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\SystemAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes append-only audit rows for sensitive system actions. Use freely:
 *   app(SystemAuditService::class)->log('settings.updated', 'setting', $id, $before, $after);
 *
 * Severity:
 *   info     = expected admin action (settings change, plan switch)
 *   warning  = noteworthy (failed login burst, permission grant)
 *   critical = security event (suspended user, key rotation)
 */
class SystemAuditService
{
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $note = null,
        string $severity = 'info',
    ): SystemAuditLog {
        return SystemAuditLog::create([
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'severity'    => $severity,
            'before'      => $before,
            'after'       => $after,
            'note'        => $note,
            'actor_id'    => Auth::id(),
            'actor_ip'    => Request::ip(),
            'user_agent'  => substr((string) Request::header('User-Agent'), 0, 255),
            'created_at'  => now(),
        ]);
    }

    public function warning(string $action, ?string $note = null, ?string $entityType = null, ?int $entityId = null): SystemAuditLog
    {
        return $this->log($action, $entityType, $entityId, null, null, $note, 'warning');
    }

    public function critical(string $action, ?string $note = null, ?string $entityType = null, ?int $entityId = null): SystemAuditLog
    {
        return $this->log($action, $entityType, $entityId, null, null, $note, 'critical');
    }
}
