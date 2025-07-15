<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class TravelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'location' => ['required', 'string'],
            'travelTo' => ['required', 'date_format:Y-m-d H:i:s'],
        ];
    }

    public function location(): string
    {
        return $this->input('location');
    }

    public function travelTo(): Carbon
    {
        return Carbon::parse($this->input('travelTo'));
    }
}
