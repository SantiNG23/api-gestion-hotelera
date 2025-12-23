<?php

declare(strict_types=1);

namespace App\Http\Requests;

class CabinPriceByGuestsRequest extends ApiRequest
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
            'price_group_id' => ['required', 'integer', 'exists:price_groups,id'],
            'num_guests' => ['required', 'integer', 'min:2', 'max:255'],
            'price_per_night' => ['required', 'numeric', 'min:0', 'max:999999.99'],
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
            'cabin_id.exists' => 'La cabaña seleccionada no existe',
            'price_group_id.required' => 'El grupo de precio es obligatorio',
            'price_group_id.exists' => 'El grupo de precio seleccionado no existe',
            'num_guests.required' => 'La cantidad de huéspedes es obligatoria',
            'num_guests.integer' => 'La cantidad de huéspedes debe ser un número entero',
            'num_guests.min' => 'La cantidad de huéspedes debe ser al menos 1',
            'price_per_night.required' => 'El precio por noche es obligatorio',
            'price_per_night.numeric' => 'El precio debe ser un número',
            'price_per_night.min' => 'El precio no puede ser negativo',
        ];
    }
}
