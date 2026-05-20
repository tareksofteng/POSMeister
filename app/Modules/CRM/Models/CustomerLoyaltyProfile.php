<?php

namespace App\Modules\CRM\Models;

use App\Modules\Sales\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerLoyaltyProfile extends Model
{
    protected $fillable = [
        'customer_id',
        'current_points',
        'lifetime_points_earned',
        'lifetime_points_redeemed',
        'lifetime_spent',
        'lifetime_visits',
        'tier',
        'tier_changed_at',
        'last_activity_at',
    ];

    protected $casts = [
        'current_points'           => 'decimal:2',
        'lifetime_points_earned'   => 'decimal:2',
        'lifetime_points_redeemed' => 'decimal:2',
        'lifetime_spent'           => 'decimal:2',
        'lifetime_visits'          => 'integer',
        'tier_changed_at'          => 'datetime',
        'last_activity_at'         => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class, 'customer_id', 'customer_id');
    }
}
