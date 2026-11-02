<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'toll_free_number' => ['required', 'regex:/^[+]?[0-9\-\s]{7,20}$/'],
            'country_code' => 'required|in:US,GB,FR,DE,IN,BR,AU,CA,JP,ZA,NL,BE,SE,NO,DK,FI,CH,AT,NZ,SG,HK,AE,SA,MX,AR,CO,CL,PE',
            'carrier' => 'nullable|string|max:100',
        ];
    }
}
