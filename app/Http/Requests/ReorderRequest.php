<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'order.required' => 'Order array is required.',
            'order.array' => 'Order must be an array.',
            'order.min' => 'Order must contain at least one item.',
        ];
    }
}
