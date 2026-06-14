<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],

        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Space name is required.',
            'name.max' => 'Space name must not exceed 255 characters.',
            'color.regex' => 'Color must be a valid hex color code.',
        ];
    }
}
