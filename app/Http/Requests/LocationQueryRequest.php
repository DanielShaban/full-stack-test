<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LocationQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'at.date' => 'The timestamp must be a valid date.',
        ];
    }
}
