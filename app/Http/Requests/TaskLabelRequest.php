<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspace = $this->route('workspace');

        return [
            'label_id' => [
                'required',
                Rule::exists('labels', 'id')->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
        ];
    }
}
