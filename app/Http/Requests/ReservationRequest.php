<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReservationRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $isPost = $this->isMethod('POST');
        $isBlocked = $this->boolean('is_blocked');
        $tenantId = Auth::user()?->tenant_id;
        $cabinExistsRule = Rule::exists('cabins', 'id');

        if ($tenantId !== null) {
            $cabinExistsRule = $cabinExistsRule->where('tenant_id', $tenantId);
        }

        $rules = [
            'cabin_id' => ['integer', $cabinExistsRule],
            'num_guests' => ['integer', 'min:2', 'max:255'],
            'check_in_date' => ['date'],
            'check_out_date' => ['date', 'after:check_in_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_blocked' => ['sometimes', 'boolean'],
            'pending_hours' => ['sometimes', 'integer', 'min:1', 'max:72'],

            // Cliente (siempre se envía el objeto client)
            'client' => ['array'],
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

        if ($isPost) {
            array_unshift($rules['cabin_id'], 'required');
            array_unshift($rules['num_guests'], 'required');
            array_unshift($rules['check_in_date'], 'required');
            $rules['check_in_date'][] = 'after_or_equal:today';
            array_unshift($rules['check_out_date'], 'required');

            if (! $isBlocked) {
                array_unshift($rules['client'], 'required');
            }
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
            'cabin_id.required' => 'La cabaña es obligatoria',
            'cabin_id.exists' => 'La cabaña no existe o no pertenece a tu organización',
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
