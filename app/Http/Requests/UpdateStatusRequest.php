<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_closed' => ['nullable', 'boolean'],
            'applies_to' => ['nullable', 'in:tasks,subtasks,both'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Status name must not exceed 255 characters.',
            'color.regex' => 'Color must be a valid hex code (e.g. #FF5733).',
            'applies_to.in' => 'Applies to must be tasks, subtasks, or both.',
        ];
    }
}
