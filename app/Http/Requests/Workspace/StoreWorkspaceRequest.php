<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreWorkspaceRequest
 *
 * Validates data for creating a new workspace.
 */
class StoreWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // All authenticated users can create workspaces
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
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
            'name.required' => 'Workspace name is required.',
            'name.max' => 'Workspace name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }
}
