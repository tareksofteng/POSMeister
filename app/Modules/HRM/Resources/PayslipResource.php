<?php

namespace App\Modules\HRM\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PayslipResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'payslip_number'     => $this->payslip_number,
            'payroll_period_id'  => $this->payroll_period_id,
            'employee_id'        => $this->employee_id,
            'branch_id'          => $this->branch_id,

            'period_start'       => $this->period_start?->format('Y-m-d'),
            'period_end'         => $this->period_end?->format('Y-m-d'),
            'period_label'       => $this->whenLoaded('period', fn() => $this->period?->label),

            'employee' => $this->whenLoaded('employee', fn() => [
                'id'           => $this->employee->id,
                'employee_id'  => $this->employee->employee_id,
                'full_name'    => $this->employee->full_name,
                'photo_url'    => $this->employee->photo ? Storage::url($this->employee->photo) : null,
                'department'   => $this->employee->department?->name,
                'designation'  => $this->employee->designation?->title,
                'shift'        => $this->employee->shift?->name,
            ]),

            'branch_name'        => $this->whenLoaded('branch', fn() => $this->branch?->name),
            'branch_address'     => $this->whenLoaded('branch', fn() => $this->branch?->address),
            'branch_phone'       => $this->whenLoaded('branch', fn() => $this->branch?->phone),

            'days_in_period'     => (int) $this->days_in_period,
            'days_worked'        => (int) $this->days_worked,
            'days_absent'        => (int) $this->days_absent,
            'days_leave'         => (int) $this->days_leave,
            'days_late'          => (int) $this->days_late,
            'days_half'          => (int) $this->days_half,

            'basic_salary'       => (float) $this->basic_salary,
            'total_allowances'   => (float) $this->total_allowances,
            'total_bonuses'      => (float) $this->total_bonuses,
            'total_overtime'     => (float) $this->total_overtime,
            'total_deductions'   => (float) $this->total_deductions,
            'tax_amount'         => (float) $this->tax_amount,
            'gross_salary'       => (float) $this->gross_salary,
            'net_salary'         => (float) $this->net_salary,

            'paid_amount'        => (float) $this->paid_amount,
            'payment_date'       => $this->payment_date?->format('Y-m-d'),
            'payment_method'     => $this->payment_method,
            'payment_reference'  => $this->payment_reference,

            'status'             => $this->status,
            'notes'              => $this->notes,

            'items'              => $this->whenLoaded('items', fn() => $this->items->map(fn($i) => [
                'id'     => $i->id,
                'type'   => $i->type,
                'name'   => $i->name,
                'amount' => (float) $i->amount,
                'notes'  => $i->notes,
            ])),

            'created_by_name'    => $this->whenLoaded('creator', fn() => $this->creator?->name),
            'created_at'         => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
