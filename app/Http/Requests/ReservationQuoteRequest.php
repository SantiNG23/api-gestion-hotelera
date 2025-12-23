<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ReservationQuoteRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'cabin_id' => ['required', 'integer', 'exists:cabins,id'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'num_guests' => ['required', 'integer', 'min:2'],
        ];
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
        ];
    }
}

