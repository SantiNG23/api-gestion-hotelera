<?php

declare(strict_types=1);

namespace App\Http\Requests;

class PriceRangeApplicableRatesRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
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
            'start_date.required' => 'La fecha de inicio es obligatoria',
            'start_date.date_format' => 'La fecha de inicio debe tener el formato YYYY-MM-DD',
            'end_date.required' => 'La fecha de fin es obligatoria',
            'end_date.date_format' => 'La fecha de fin debe tener el formato YYYY-MM-DD',
            'end_date.after_or_equal' => 'La fecha de fin debe ser mayor o igual a la fecha de inicio',
        ];
    }
}
