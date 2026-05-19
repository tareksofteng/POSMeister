<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                        => $this->id,
            'title'                     => $this->title,
            'fiscal_year'               => (int) $this->fiscal_year,
            'start_date'                => $this->start_date?->format('Y-m-d'),
            'end_date'                  => $this->end_date?->format('Y-m-d'),
            'branch_id'                 => $this->branch_id,
            'branch_name'               => $this->whenLoaded('branch', fn() => $this->branch?->name),
            'total_budget'              => (float) $this->total_budget,
            'warning_threshold_percent' => (int) $this->warning_threshold_percent,
            'status'                    => $this->status,
            'notes'                     => $this->notes,

            'items_count'  => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($i) => [
                'id'                  => $i->id,
                'expense_category_id' => $i->expense_category_id,
                'category_name'       => $i->category?->name,
                'category_code'       => $i->category?->code,
                'allocated_amount'    => (float) $i->allocated_amount,
            ])),

            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
