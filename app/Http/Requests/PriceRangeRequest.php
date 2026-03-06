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
        $isPost = $this->isMethod('POST');

        $rules = [
            'price_group_id' => ['integer', 'exists:price_groups,id'],
            'start_date' => ['date'],
            'end_date' => ['date', 'after:start_date'],
        ];

        if ($isPost) {
            array_unshift($rules['price_group_id'], 'required');
            array_unshift($rules['start_date'], 'required');
            $rules['start_date'][] = 'after_or_equal:today';
            array_unshift($rules['end_date'], 'required');
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
