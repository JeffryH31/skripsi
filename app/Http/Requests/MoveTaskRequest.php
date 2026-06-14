<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $space = $this->route('space');

        return [
            'list_id' => [
                'required',
                Rule::exists('projects', 'id')->where(fn ($query) => $query->where('space_id', $space->id)),
            ],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
