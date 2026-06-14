<?php

namespace App\Http\Requests;

use App\Models\Status;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status_id' => [
                'nullable',
                'exists:statuses,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $status = Status::find($value);
                        if ($status && $status->applies_to === 'subtasks') {
                            $fail('This status can only be used for subtasks, not tasks.');
                        }
                    }
                },
            ],
            'priority_level' => ['nullable', 'integer', 'in:1,2,3,4'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'time_estimate' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'exists:labels,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Task name is required.',
            'name.max' => 'Task name must not exceed 255 characters.',
            'status_id.exists' => 'Selected status does not exist.',
            'due_date.after_or_equal' => 'Due date must be on or after the start date.',
        ];
    }
}
