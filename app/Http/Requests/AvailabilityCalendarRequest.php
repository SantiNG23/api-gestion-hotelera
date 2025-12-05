<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Validación para obtener el calendario de disponibilidad
 * GET /availability/calendar?from=...&to=...
 */
class AvailabilityCalendarRequest extends ApiRequest
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
            'from' => ['required', 'date', 'date_format:Y-m-d'],
            'to' => ['required', 'date', 'date_format:Y-m-d', 'after:from'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'from.required' => 'La fecha de inicio es requerida',
            'from.date' => 'La fecha de inicio debe ser una fecha válida',
            'from.date_format' => 'La fecha de inicio debe estar en formato Y-m-d',
            'to.required' => 'La fecha de fin es requerida',
            'to.date' => 'La fecha de fin debe ser una fecha válida',
            'to.date_format' => 'La fecha de fin debe estar en formato Y-m-d',
            'to.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
        ];
    }
}

