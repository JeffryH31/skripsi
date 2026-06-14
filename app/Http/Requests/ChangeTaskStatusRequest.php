<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $space = $this->route('space');

        return [
            'status_id' => [
                'required',
                Rule::exists('statuses', 'id')->where(function ($query) use ($space) {
                    $query->where('space_id', $space->id)
                        ->whereIn('applies_to', ['tasks', 'both']);
                }),
            ],
        ];
    }
}
