<?php

namespace App\Modules\Settings\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'company_name'    => $this->company_name,
            'address'         => $this->address,
            'phone'           => $this->phone,
            'email'           => $this->email,
            'logo'            => $this->logo,
            'logo_url'        => $this->logo ? '/storage/' . $this->logo : null,
            'currency_code'   => $this->currency_code,
            'currency_symbol' => $this->currency_symbol,
            'vat_default'     => (float) $this->vat_default,
            'invoice_prefix'  => $this->invoice_prefix,
            'invoice_footer'  => $this->invoice_footer,
            'date_format'     => $this->date_format,
        ];
    }
}
