<?php

namespace App\Http\Requests;

use App\Models\Status;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:subtasks,id'],
            'status_id' => [
                'nullable',
                'exists:statuses,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $status = Status::find($value);
                        if ($status && $status->applies_to === 'tasks') {
                            $fail('This status can only be used for tasks, not subtasks.');
                        }
                    }
                },
            ],
            'task_id' => ['required', 'exists:tasks,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority_level' => ['nullable', 'integer', 'in:1,2,3,4'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'time_estimate' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'assignee_ids' => ['nullable', 'array', 'max:1'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'exists:labels,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Subtask name is required.',
            'name.max' => 'Subtask name must not exceed 255 characters.',
            'status_id.exists' => 'Selected status does not exist.',
            'task_id.required' => 'Task ID is required.',
            'task_id.exists' => 'Parent task does not exist.',
            'assignee_ids.max' => 'A subtask can only have one assignee.',
            'due_date.after_or_equal' => 'Due date must be on or after the start date.',
        ];
    }
}
