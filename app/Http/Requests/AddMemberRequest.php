<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Accept either user_id (existing user) or email (invite flow)
            'user_id' => ['required_without:email', 'nullable', 'exists:users,id'],
            'email'   => ['required_without:user_id', 'nullable', 'email'],
            'role'    => ['nullable', 'string', 'in:admin,member'],
        ];
    }
}
