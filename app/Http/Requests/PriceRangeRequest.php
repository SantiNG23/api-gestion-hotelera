<?php

declare(strict_types=1);

namespace App\Http\Requests;

class PriceRangeRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'price_group_id' => ['required', 'integer', 'exists:price_groups,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];

        // En update, los campos no son obligatorios
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['price_group_id'][0] = 'sometimes';
            $rules['start_date'][0] = 'sometimes';
            $rules['end_date'][0] = 'sometimes';
            // Permitir fechas en el pasado para ediciones
            $rules['start_date'] = ['sometimes', 'date'];
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
            'price_group_id.required' => 'El grupo de precio es obligatorio',
            'price_group_id.exists' => 'El grupo de precio no existe',
            'start_date.required' => 'La fecha de inicio es obligatoria',
            'start_date.after_or_equal' => 'La fecha de inicio debe ser hoy o posterior',
            'end_date.required' => 'La fecha de fin es obligatoria',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
        ];
    }
}

