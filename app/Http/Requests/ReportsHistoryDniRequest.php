<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ReportsHistoryDniRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'dni' => ['required', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'dni.required' => 'El DNI es requerido',
            'dni.string' => 'El DNI debe ser un texto valido',
            'dni.max' => 'El DNI no puede superar los 32 caracteres',
        ];
    }
}
