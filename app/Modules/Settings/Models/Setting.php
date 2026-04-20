<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row configuration table. Always use Setting::sole() or Setting::first()
 * — there is exactly one row (seeded by the migration).
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'company_name', 'address', 'phone', 'email', 'logo',
        'currency_code', 'currency_symbol',
        'vat_default',
        'invoice_prefix', 'invoice_footer', 'date_format',
    ];

    protected $casts = [
        'vat_default' => 'decimal:2',
    ];
}
