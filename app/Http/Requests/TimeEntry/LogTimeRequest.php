<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntry;

use Illuminate\Foundation\Http\FormRequest;

/**
 * LogTimeRequest
 *
 * Validates data for logging manual time entry.
 */
class LogTimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'task_id' => ['required', 'exists:tasks,id'],
            'hours' => ['required_without:duration_minutes', 'numeric', 'min:0.01', 'max:24'],
            'duration_minutes' => ['required_without:hours', 'integer', 'min:1', 'max:1440'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logged_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert hours to duration_minutes if hours is provided
        if ($this->has('hours') && ! $this->has('duration_minutes')) {
            $this->merge([
                'duration_minutes' => (int) round($this->hours * 60),
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'task_id.required' => 'Task is required.',
            'task_id.exists' => 'Task not found.',
            'duration_minutes.required' => 'Duration is required.',
            'duration_minutes.min' => 'Duration must be at least 1 minute.',
            'duration_minutes.max' => 'Duration cannot exceed 1440 minutes (24 hours).',
            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }
}
