<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscoveryJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'languages' => ['sometimes', 'array', 'max:10'],
            'languages.*' => ['string', 'regex:/^[a-z]{2}(-[A-Z]{2})?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.regex' => 'The phone number must be in E.164 format (e.g. +18005551234).',
            'country_code.regex' => 'The country code must be a 2-letter ISO 3166-1 alpha-2 code (e.g. US, GB).',
            'languages.max' => 'A maximum of 10 languages may be specified.',
            'languages.*.regex' => 'Each language tag must be a valid BCP-47 code (e.g. en, fr, en-US).',
        ];
    }
}
