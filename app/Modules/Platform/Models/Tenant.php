<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'subdomain', 'contact_email', 'phone',
        'country', 'currency', 'timezone', 'locale',
        'status', 'trial_ends_at', 'settings',
    ];

    protected $casts = [
        'settings'      => 'array',
        'trial_ends_at' => 'date',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->where('status', 'active')->latest('starts_at')->first();
    }
}
