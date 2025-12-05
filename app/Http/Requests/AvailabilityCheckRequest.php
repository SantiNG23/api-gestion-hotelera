<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Validación para verificar disponibilidad de cabañas
 * GET /availability?check_in_date=...&check_out_date=...&cabin_id=...
 */
class AvailabilityCheckRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'check_in_date' => ['required', 'date', 'date_format:Y-m-d'],
            'check_out_date' => ['required', 'date', 'date_format:Y-m-d', 'after:check_in_date'],
            'cabin_id' => ['nullable', 'integer', 'exists:cabins,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'check_in_date.required' => 'La fecha de check-in es requerida',
            'check_in_date.date' => 'La fecha de check-in debe ser una fecha válida',
            'check_in_date.date_format' => 'La fecha de check-in debe estar en formato Y-m-d',
            'check_out_date.required' => 'La fecha de check-out es requerida',
            'check_out_date.date' => 'La fecha de check-out debe ser una fecha válida',
            'check_out_date.date_format' => 'La fecha de check-out debe estar en formato Y-m-d',
            'check_out_date.after' => 'La fecha de check-out debe ser posterior a la fecha de check-in',
            'cabin_id.integer' => 'El ID de cabaña debe ser un número entero',
            'cabin_id.exists' => 'La cabaña especificada no existe',
        ];
    }
}
