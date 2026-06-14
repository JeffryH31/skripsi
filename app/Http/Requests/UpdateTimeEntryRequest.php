<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'duration' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'is_billable' => ['nullable', 'boolean'],
        ];
    }
}
