<?php

declare(strict_types=1);

namespace App\Http\Requests\Label;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreLabelRequest
 *
 * Validates data for creating a new label.
 */
class StoreLabelRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'max:7'],
            'board_id' => ['required', 'exists:boards,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Label name is required.',
            'name.max' => 'Label name cannot exceed 100 characters.',
            'color.required' => 'Label color is required.',
            'board_id.required' => 'Board is required.',
            'board_id.exists' => 'Board not found.',
        ];
    }
}
