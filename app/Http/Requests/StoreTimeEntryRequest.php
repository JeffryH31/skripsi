<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'duration' => ['required', 'integer', 'min:1', 'max:1440'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'duration.required' => 'Duration is required.',
            'duration.min' => 'Duration must be at least 1 minute.',
            'duration.max' => 'Duration cannot exceed 1440 minutes (24 hours).',
            'started_at.date' => 'Start time must be a valid date.',
            'ended_at.after' => 'End time must be after start time.',
        ];
    }
}
