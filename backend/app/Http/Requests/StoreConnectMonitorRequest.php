<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConnectMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'toll_free_number' => ['required', 'string', 'regex:/^\+?[0-9\s\-\(\)]{7,20}$/'],
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'carrier' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'toll_free_number.regex' => 'The toll free number must be 7–20 digits and may include +, spaces, hyphens, or parentheses.',
            'country_code.regex' => 'The country code must be a 2-letter ISO 3166-1 alpha-2 code (e.g. US, GB).',
        ];
    }
}
