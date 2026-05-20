<?php

namespace App\Modules\CRM\Models;

use App\Modules\Sales\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerWallet extends Model
{
    protected $fillable = [
        'customer_id', 'balance',
        'lifetime_credited', 'lifetime_debited',
        'allow_negative', 'currency',
    ];

    protected $casts = [
        'balance'           => 'decimal:2',
        'lifetime_credited' => 'decimal:2',
        'lifetime_debited'  => 'decimal:2',
        'allow_negative'    => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'customer_id', 'customer_id');
    }
}
