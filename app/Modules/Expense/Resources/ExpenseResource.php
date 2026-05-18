<?php

namespace App\Modules\Expense\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ExpenseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'expense_number'      => $this->expense_number,

            'branch_id'           => $this->branch_id,
            'branch_name'         => $this->whenLoaded('branch', fn() => $this->branch?->name),

            'expense_category_id' => $this->expense_category_id,
            'category' => $this->whenLoaded('category', fn() => $this->category ? [
                'id'   => $this->category->id,
                'name' => $this->category->name,
                'code' => $this->category->code,
            ] : null),

            'title'           => $this->title,
            'description'     => $this->description,
            'amount'          => (float) $this->amount,
            'expense_date'    => $this->expense_date?->format('Y-m-d'),
            'payment_method'  => $this->payment_method,
            'reference_no'    => $this->reference_no,

            'attachment'      => $this->attachment,
            'attachment_url'  => $this->attachment ? Storage::url($this->attachment) : null,

            'status'          => $this->status,

            'created_by_name' => $this->whenLoaded('creator', fn() => $this->creator?->name, '—'),
            'created_at'      => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
