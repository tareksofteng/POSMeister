<?php

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SettingsService
{
    /** Always returns the single settings row. */
    public function get(): Setting
    {
        return Setting::firstOrCreate(
            ['id' => 1],
            [
                'company_name'    => 'POSmeister',
                'currency_code'   => 'EUR',
                'currency_symbol' => '€',
                'vat_default'     => 19.00,
                'invoice_prefix'  => 'INV-',
                'date_format'     => 'd.m.Y',
            ]
        );
    }

    /** Update text fields. */
    public function update(array $validated): Setting
    {
        $settings = $this->get();
        $settings->update($validated);
        return $settings->fresh();
    }

    /** Replace the logo file, delete the old one. */
    public function uploadLogo(\Illuminate\Http\UploadedFile $file): Setting
    {
        $settings = $this->get();

        if ($settings->logo) {
            Storage::disk('public')->delete($settings->logo);
        }

        $path = $file->store('settings', 'public');
        $settings->update(['logo' => $path]);

        return $settings->fresh();
    }

    /** Remove the logo file. */
    public function deleteLogo(): Setting
    {
        $settings = $this->get();

        if ($settings->logo) {
            Storage::disk('public')->delete($settings->logo);
            $settings->update(['logo' => null]);
        }

        return $settings->fresh();
    }
}
