<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $subtask = $this->route('subtask');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status_id' => ['sometimes', 'exists:statuses,id'],
            'priority_level' => ['nullable', 'integer', 'in:1,2,3,4'],
            'start_date' => ['nullable', 'date'],
            'due_date' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) use ($subtask) {
                    if (! $value) {
                        return;
                    }
                    $startDate = $this->input('start_date', $subtask->start_date);
                    if ($startDate && substr($value, 0, 10) < substr((string) $startDate, 0, 10)) {
                        $fail('Due date must be after or equal to start date.');
                    }
                },
            ],
            'baseline_start_date' => ['nullable', 'date'],
            'baseline_due_date' => ['nullable', 'date', 'after_or_equal:baseline_start_date'],
            'time_estimate' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'optimistic_estimate' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'most_likely_estimate' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'pessimistic_estimate' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'sprint_id' => ['nullable', 'exists:sprints,id'],
            'assignee_ids' => ['sometimes', 'array', 'max:1'],
            'assignee_ids.*' => ['exists:users,id'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['exists:labels,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Subtask name must not exceed 255 characters.',
            'due_date.date' => 'Due date must be a valid date.',
            'time_estimate.max' => 'Time estimate cannot exceed 1 year.',
            'baseline_due_date.after_or_equal' => 'Baseline due date must be after or equal to baseline start date.',
            'status_id.exists' => 'Selected status does not exist.',
            'priority_level.in' => 'Selected priority does not exist.',
        ];
    }
}
