<?php

namespace App\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('shift')?->id;

        return [
            'name'          => ['required', 'string', 'max:80', "unique:shifts,name,{$id},id,deleted_at,NULL"],
            'start_time'    => ['required', 'date_format:H:i'],
            'end_time'      => ['required', 'date_format:H:i'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }
}
