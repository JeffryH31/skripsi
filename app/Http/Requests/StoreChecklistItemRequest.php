<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:500'],
            'parent_id' => ['nullable', 'exists:checklist_items,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Checklist item name is required.',
            'name.max' => 'Checklist item name must not exceed 500 characters.',
            'parent_id.exists' => 'Parent checklist item does not exist.',
        ];
    }
}
