<?php

namespace App\Modules\HRM\Services;

use App\Modules\HRM\Models\HrAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Writes immutable audit log rows for sensitive HR actions.
 * Designed for fire-and-forget use from other services.
 */
class HrAuditService
{
    public function log(string $action, string $entityType, int $entityId, ?array $before = null, ?array $after = null, ?string $note = null): HrAuditLog
    {
        return HrAuditLog::create([
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'before'      => $before,
            'after'       => $after,
            'note'        => $note,
            'actor_id'    => Auth::id(),
            'actor_ip'    => request()?->ip(),
            'created_at'  => now(),
        ]);
    }
}
