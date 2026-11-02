<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone_number' => ['required', 'regex:/^\+?[1-9]\d{6,14}$/'],
            'country_code' => 'required|in:US,GB,FR,DE,IN,BR,AU,CA,JP,ZA,NL,BE,SE,NO,DK,FI,CH,AT,NZ,SG,HK,AE,SA,MX,AR,CO,CL,PE',
            'languages' => 'array|min:1',
            'languages.*' => 'in:en,fr,de,es,pt,zh,ar,ja,ko,hi',
        ];
    }
}
