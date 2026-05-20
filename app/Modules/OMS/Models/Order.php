<?php

namespace App\Modules\OMS\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Modules\Sales\Models\Customer;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes, HasAuditFields;

    public const STATUSES = ['pending', 'confirmed', 'packed', 'shipped', 'delivered', 'cancelled', 'returned'];

    protected $fillable = [
        'order_number', 'customer_id', 'branch_id',
        'source', 'status',
        'payment_status', 'payment_method',
        'subtotal', 'discount', 'shipping_cost', 'vat_amount', 'total', 'paid_amount',
        'customer_name', 'customer_phone',
        'delivery_address', 'delivery_city', 'delivery_zip',
        'notes', 'external_reference',
        'placed_at', 'confirmed_at', 'packed_at', 'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'discount'      => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'vat_amount'    => 'decimal:2',
        'total'         => 'decimal:2',
        'paid_amount'   => 'decimal:2',
        'placed_at'     => 'datetime',
        'confirmed_at'  => 'datetime',
        'packed_at'     => 'datetime',
        'shipped_at'    => 'datetime',
        'delivered_at'  => 'datetime',
        'cancelled_at'  => 'datetime',
    ];

    public function items(): HasMany       { return $this->hasMany(OrderItem::class); }
    public function logs(): HasMany        { return $this->hasMany(OrderStatusLog::class)->orderBy('created_at'); }
    public function shipment(): HasOne     { return $this->hasOne(Shipment::class); }
    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function branch(): BelongsTo    { return $this->belongsTo(Branch::class); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }

    public function isOpen(): bool
    {
        return !in_array($this->status, ['delivered', 'cancelled', 'returned'], true);
    }
}
