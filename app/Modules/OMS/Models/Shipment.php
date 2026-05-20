<?php

namespace App\Modules\OMS\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id', 'courier_id',
        'tracking_number', 'status',
        'shipping_cost', 'label_url',
        'dispatched_at', 'delivered_at',
        'provider_payload', 'last_error',
        'created_by',
    ];

    protected $casts = [
        'shipping_cost'    => 'decimal:2',
        'provider_payload' => 'array',
        'dispatched_at'    => 'datetime',
        'delivered_at'     => 'datetime',
    ];

    public function order(): BelongsTo   { return $this->belongsTo(Order::class); }
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
