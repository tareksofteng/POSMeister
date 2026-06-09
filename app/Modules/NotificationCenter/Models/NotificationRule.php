<?php

namespace App\Modules\NotificationCenter\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/*
 |--------------------------------------------------------------------------
 | NotificationRule — Phase AB Round 3
 |--------------------------------------------------------------------------
 |
 | One row per detector code that an admin has chosen to override. Read
 | by NotificationRuleEngine inside SmartNotificationService::push().
 |
 | NULL on any override column = use detector default. Only the columns
 | the admin explicitly sets take effect, so partial overrides (e.g.
 | "raise cooldown to 24h but leave thresholds alone") work cleanly.
 */
class NotificationRule extends Model
{
    protected $table = 'notification_rules';

    protected $fillable = [
        'code', 'enabled',
        'cooldown_minutes',
        'warning_threshold', 'danger_threshold', 'critical_threshold',
        'min_severity', 'max_severity',
        'audience_role', 'branch_ids',
        'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'enabled'            => 'boolean',
        'cooldown_minutes'   => 'integer',
        'warning_threshold'  => 'integer',
        'danger_threshold'   => 'integer',
        'critical_threshold' => 'integer',
        'branch_ids'         => 'array',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }

    /**
     * Does this rule's branch filter accept the supplied branch_id?
     *
     *   - rule branch_ids = NULL          → always applies
     *   - rule branch_ids = [2, 3]        → applies only when payload
     *                                       branch_id ∈ {2, 3}
     *   - notification has no branch_id   → rule applies (global alert)
     */
    public function matchesBranch(?int $branchId): bool
    {
        $list = $this->branch_ids;
        if (empty($list))   return true;
        if ($branchId === null) return true;
        return in_array($branchId, $list, true);
    }
}
