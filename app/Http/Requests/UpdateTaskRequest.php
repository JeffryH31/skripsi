<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'priority_level' => ['nullable', 'integer', 'in:1,2,3,4'],
            'start_date' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) use ($task) {
                    if (! $value) {
                        return;
                    }
                    $dueDate = $this->input('due_date', $task?->due_date?->format('Y-m-d'));
                    if ($dueDate && $value > $dueDate) {
                        $fail('Start date must be before or equal to due date.');
                    }
                },
            ],
            'due_date' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) use ($task) {
                    if (! $value) {
                        return;
                    }
                    $startDate = $this->input('start_date', $task?->start_date?->format('Y-m-d'));
                    if ($startDate && $value < $startDate) {
                        $fail('Due date must be after or equal to start date.');
                    }
                },
            ],
            'time_estimate' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['exists:users,id'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['exists:labels,id'],
        ];
    }
}
