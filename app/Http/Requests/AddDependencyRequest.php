<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subtask_id' => ['required', 'exists:subtasks,id'],
            'depends_on_id' => ['required', 'exists:subtasks,id'],
            'type' => ['nullable', 'in:blocks,relates_to'],
        ];
    }
}
