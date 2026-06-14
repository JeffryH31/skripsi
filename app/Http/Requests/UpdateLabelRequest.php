<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspace = $this->route('workspace');
        $label = $this->route('label');

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('labels', 'name')
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id))
                    ->ignore($label->id),
            ],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
