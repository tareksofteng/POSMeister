<?php

namespace App\Modules\Serials\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 |--------------------------------------------------------------------------
 | ProductSerialMovement — append-only audit log of every serial event
 |--------------------------------------------------------------------------
 |
 | Rows are inserted by SerialMovementService::log() and never updated
 | or deleted. The table's UPDATED_AT is deliberately disabled to enforce
 | the "rows are immutable" contract via the model layer too.
 */
class ProductSerialMovement extends Model
{
    public const MOVEMENT_PURCHASE         = 'purchase';
    public const MOVEMENT_SALE             = 'sale';
    public const MOVEMENT_PURCHASE_RETURN  = 'purchase_return';
    public const MOVEMENT_SALES_RETURN     = 'sales_return';
    public const MOVEMENT_TRANSFER         = 'transfer';
    public const MOVEMENT_RESERVE          = 'reserve';
    public const MOVEMENT_UNRESERVE        = 'unreserve';
    public const MOVEMENT_DAMAGE           = 'damage';
    public const MOVEMENT_LOST             = 'lost';

    public $timestamps = false;             // created_at only — no updated_at

    protected $fillable = [
        'product_serial_id',
        'movement_type',
        'reference_type', 'reference_id',
        'from_branch_id', 'to_branch_id',
        'remarks',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function serial(): BelongsTo
    {
        return $this->belongsTo(ProductSerial::class, 'product_serial_id');
    }
}
