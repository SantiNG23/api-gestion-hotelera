<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ReservationPaymentRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
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
            'payment_method.max' => 'El método de pago no puede superar los 100 caracteres',
            'paid_at.date' => 'La fecha de pago no tiene un formato válido',
        ];
    }
}

