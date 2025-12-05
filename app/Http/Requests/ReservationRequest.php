<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ReservationRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'cabin_id' => ['required', 'integer', 'exists:cabins,id'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'pending_hours' => ['sometimes', 'integer', 'min:1', 'max:72'],

            // Huéspedes
            'guests' => ['sometimes', 'array'],
            'guests.*.name' => ['required_with:guests', 'string', 'max:255'],
            'guests.*.dni' => ['required_with:guests', 'string', 'max:20'],
            'guests.*.age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'guests.*.city' => ['nullable', 'string', 'max:255'],
            'guests.*.phone' => ['nullable', 'string', 'max:50'],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
        ];

        // En update, los campos no son obligatorios
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['client_id'][0] = 'sometimes';
            $rules['cabin_id'][0] = 'sometimes';
            $rules['check_in_date'][0] = 'sometimes';
            $rules['check_out_date'][0] = 'sometimes';
            // En update permitir fechas pasadas (reservas existentes)
            $rules['check_in_date'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'El cliente es obligatorio',
            'client_id.exists' => 'El cliente no existe',
            'cabin_id.required' => 'La cabaña es obligatoria',
            'cabin_id.exists' => 'La cabaña no existe',
            'check_in_date.required' => 'La fecha de check-in es obligatoria',
            'check_in_date.after_or_equal' => 'La fecha de check-in debe ser hoy o posterior',
            'check_out_date.required' => 'La fecha de check-out es obligatoria',
            'check_out_date.after' => 'La fecha de check-out debe ser posterior al check-in',
            'guests.*.name.required_with' => 'El nombre del huésped es obligatorio',
            'guests.*.dni.required_with' => 'El DNI del huésped es obligatorio',
        ];
    }
}

