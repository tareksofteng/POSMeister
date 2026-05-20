<?php

namespace App\Modules\CRM\Controllers;

use App\Modules\CRM\Models\LoyaltySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LoyaltySettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => LoyaltySettings::current()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled'                    => 'boolean',
            'earn_per_currency'          => 'numeric|min:0|max:1000',
            'redeem_points_per_currency' => 'integer|min:1|max:100000',
            'min_redeem_points'          => 'integer|min:0',
            'points_expiry_months'       => 'integer|min:0|max:120',
            'tier_silver_min'            => 'numeric|min:0',
            'tier_gold_min'              => 'numeric|min:0',
            'tier_platinum_min'          => 'numeric|min:0',
            'tier_vip_min'               => 'numeric|min:0',
            'tier_silver_discount'       => 'numeric|min:0|max:100',
            'tier_gold_discount'         => 'numeric|min:0|max:100',
            'tier_platinum_discount'     => 'numeric|min:0|max:100',
            'tier_vip_discount'          => 'numeric|min:0|max:100',
            'auto_downgrade'             => 'boolean',
        ]);

        $settings = LoyaltySettings::current();
        $settings->update($data);

        return response()->json(['data' => $settings->fresh()]);
    }
}
