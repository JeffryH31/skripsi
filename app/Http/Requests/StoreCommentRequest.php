<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
            'subtask_id' => ['nullable', 'exists:subtasks,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Comment content is required.',
            'content.max' => 'Comment must not exceed 10000 characters.',
        ];
    }
}
