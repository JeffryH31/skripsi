<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * AddWorkspaceMemberRequest
 *
 * Validates data for adding a member to a workspace.
 */
class AddWorkspaceMemberRequest extends FormRequest
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
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['sometimes', 'string', Rule::in(['admin', 'member'])],
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
            'email.required' => 'Email is required.',
            'email.email' => 'Email format is invalid.',
            'email.exists' => 'Email not found.',
            'role.in' => 'Role must be admin or member.',
        ];
    }
}
