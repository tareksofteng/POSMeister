<?php

namespace App\Modules\HRM\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'label'          => $this->label,
            'period_start'   => $this->period_start?->format('Y-m-d'),
            'period_end'     => $this->period_end?->format('Y-m-d'),
            'status'         => $this->status,
            'branch_id'      => $this->branch_id,
            'branch_name'    => $this->whenLoaded('branch', fn() => $this->branch?->name),
            'notes'          => $this->notes,
            'payslips_count' => $this->whenCounted('payslips'),
            'paid_count'     => $this->paid_count ?? null,
            'net_total'      => isset($this->net_total) ? (float) $this->net_total : null,
            'created_at'     => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
