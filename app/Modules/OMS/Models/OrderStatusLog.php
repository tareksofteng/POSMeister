<?php

namespace App\Modules\OMS\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'from_status', 'to_status', 'note', 'created_by', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo   { return $this->belongsTo(Order::class); }
    public function author(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
}
