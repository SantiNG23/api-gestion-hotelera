<?php

declare(strict_types=1);

namespace App\Http\Requests;

class PriceGroupRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price_per_night' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
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
            'name.required' => 'El nombre es obligatorio',
            'price_per_night.required' => 'El precio por noche es obligatorio',
            'price_per_night.numeric' => 'El precio debe ser un número',
            'price_per_night.min' => 'El precio no puede ser negativo',
        ];
    }
}

