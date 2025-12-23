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
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        $checkInRules = $isUpdate
            ? ['sometimes', 'date']
            : ['required', 'date', 'after_or_equal:today'];

        $checkOutRules = $isUpdate
            ? ['sometimes', 'date', 'after:check_in_date']
            : ['required', 'date', 'after:check_in_date'];

        $rules = [
            'cabin_id' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'exists:cabins,id'],
            'num_guests' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:2', 'max:255'],
            'check_in_date' => $checkInRules,
            'check_out_date' => $checkOutRules,
            'notes' => ['nullable', 'string', 'max:2000'],
            'pending_hours' => ['sometimes', 'integer', 'min:1', 'max:72'],

            // Cliente (siempre se envía el objeto client)
            'client' => [$isUpdate ? 'sometimes' : 'required', 'array'],
            'client.name' => ['required_with:client', 'string', 'max:255'],
            'client.dni' => ['required_with:client', 'string', 'max:20'],
            'client.age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'client.city' => ['nullable', 'string', 'max:255'],
            'client.phone' => ['nullable', 'string', 'max:50'],
            'client.email' => ['nullable', 'email', 'max:255'],

            // Huéspedes
            'guests' => ['sometimes', 'array'],
            'guests.*.name' => ['required_with:guests', 'string', 'max:255'],
            'guests.*.dni' => ['required_with:guests', 'string', 'max:20'],
            'guests.*.age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'guests.*.city' => ['nullable', 'string', 'max:255'],
            'guests.*.phone' => ['nullable', 'string', 'max:50'],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
        ];
        // En update permitir fechas pasadas (reservas existentes)
        // (ya manejado en $checkInRules / $checkOutRules)

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
            'cabin_id.required' => 'La cabaña es obligatoria',
            'cabin_id.exists' => 'La cabaña no existe',
            'check_in_date.required' => 'La fecha de check-in es obligatoria',
            'check_in_date.after_or_equal' => 'La fecha de check-in debe ser hoy o posterior',
            'check_out_date.required' => 'La fecha de check-out es obligatoria',
            'check_out_date.after' => 'La fecha de check-out debe ser posterior al check-in',
            'client.required' => 'Los datos del cliente son obligatorios',
            'client.name.required_with' => 'El nombre del cliente es obligatorio',
            'client.dni.required_with' => 'El DNI del cliente es obligatorio',
            'guests.*.name.required_with' => 'El nombre del huésped es obligatorio',
            'guests.*.dni.required_with' => 'El DNI del huésped es obligatorio',
        ];
    }
}
