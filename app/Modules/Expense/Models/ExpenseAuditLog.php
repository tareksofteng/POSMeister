<?php

namespace App\Modules\Expense\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['expense_id', 'user_id', 'action', 'notes', 'payload', 'created_at'];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
