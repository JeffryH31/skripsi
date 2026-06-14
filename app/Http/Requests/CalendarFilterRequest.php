<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalendarFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'view' => ['nullable', 'string', 'in:month,week,day'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'view.in' => 'View must be month, week, or day.',
        ];
    }
}
