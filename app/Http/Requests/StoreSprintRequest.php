<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'list_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'list_id.required' => 'Project is required.',
            'name.required' => 'Sprint name is required.',
            'name.max' => 'Sprint name must not exceed 255 characters.',
            'start_date.required' => 'Start date is required.',
            'end_date.required' => 'End date is required.',
            'end_date.after' => 'End date must be after start date.',
            'goal.max' => 'Goal must not exceed 1000 characters.',
        ];
    }
}
