<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'list_id' => ['sometimes', 'integer', 'exists:projects,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Sprint name must not exceed 255 characters.',
            'end_date.after' => 'End date must be after start date.',
            'goal.max' => 'Goal must not exceed 1000 characters.',
        ];
    }
}
