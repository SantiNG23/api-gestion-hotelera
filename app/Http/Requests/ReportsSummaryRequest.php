<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReportsSummaryRequest extends ApiRequest
{
    public function rules(): array
    {
        $tenantId = Auth::user()?->tenant_id;
        $cabinExistsRule = Rule::exists('cabins', 'id');

        if ($tenantId !== null) {
            $cabinExistsRule = $cabinExistsRule->where('tenant_id', $tenantId);
        }

        return [
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'cabin_id' => ['nullable', 'integer', $cabinExistsRule],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'La fecha de inicio es requerida',
            'start_date.date' => 'La fecha de inicio debe ser una fecha valida',
            'start_date.date_format' => 'La fecha de inicio debe estar en formato Y-m-d',
            'end_date.required' => 'La fecha de fin es requerida',
            'end_date.date' => 'La fecha de fin debe ser una fecha valida',
            'end_date.date_format' => 'La fecha de fin debe estar en formato Y-m-d',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio',
            'cabin_id.integer' => 'El ID de cabana debe ser un numero entero',
            'cabin_id.exists' => 'La cabana especificada no existe o no pertenece a tu organizacion',
        ];
    }
}
