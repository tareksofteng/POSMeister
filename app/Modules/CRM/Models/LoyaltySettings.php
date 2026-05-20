<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltySettings extends Model
{
    protected $table = 'loyalty_settings';

    protected $fillable = [
        'enabled',
        'earn_per_currency',
        'redeem_points_per_currency',
        'min_redeem_points',
        'points_expiry_months',
        'tier_silver_min', 'tier_gold_min', 'tier_platinum_min', 'tier_vip_min',
        'tier_silver_discount', 'tier_gold_discount', 'tier_platinum_discount', 'tier_vip_discount',
        'auto_downgrade',
    ];

    protected $casts = [
        'enabled'                    => 'boolean',
        'auto_downgrade'             => 'boolean',
        'earn_per_currency'          => 'decimal:4',
        'redeem_points_per_currency' => 'integer',
        'min_redeem_points'          => 'integer',
        'points_expiry_months'       => 'integer',
        'tier_silver_min'            => 'decimal:2',
        'tier_gold_min'              => 'decimal:2',
        'tier_platinum_min'          => 'decimal:2',
        'tier_vip_min'               => 'decimal:2',
        'tier_silver_discount'       => 'decimal:2',
        'tier_gold_discount'         => 'decimal:2',
        'tier_platinum_discount'     => 'decimal:2',
        'tier_vip_discount'          => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
