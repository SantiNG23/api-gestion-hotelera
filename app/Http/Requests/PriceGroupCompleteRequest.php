<?php

declare(strict_types=1);

namespace App\Http\Requests;

class PriceGroupCompleteRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $priceGroupId = $this->route('id');

        $nameRule = $isUpdate
            ? 'string|max:255|unique:price_groups,name,' . $priceGroupId . ',id,tenant_id,' . auth()->user()->tenant_id
            : 'required|string|max:255|unique:price_groups,name,NULL,id,tenant_id,' . auth()->user()->tenant_id;

        return [
            'name' => $nameRule,
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['boolean'],
            'cabins' => $isUpdate ? ['array', 'min:1'] : ['required', 'array', 'min:1'],
            'cabins.*.cabin_id' => ['required_with:cabins', 'integer', 'exists:cabins,id'],
            'cabins.*.prices' => ['required_with:cabins', 'array', 'min:1'],
            'cabins.*.prices.*.num_guests' => ['required_with:cabins', 'integer', 'min:2', 'max:255'],
            'cabins.*.prices.*.price_per_night' => ['required_with:cabins', 'numeric', 'min:0', 'max:999999.99'],
            'date_ranges' => ['array'],
            'date_ranges.*.start_date' => ['required_with:date_ranges', 'date', 'date_format:Y-m-d'],
            'date_ranges.*.end_date' => ['required_with:date_ranges', 'date', 'date_format:Y-m-d', 'after:date_ranges.*.start_date'],
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
            'name.required' => 'El nombre del grupo de precio es obligatorio',
            'name.unique' => 'Ya existe un grupo de precio con ese nombre',
            'cabins.required' => 'Debe especificar al menos una cabaña',
            'cabins.*.cabin_id.required_with' => 'El ID de la cabaña es obligatorio',
            'cabins.*.cabin_id.exists' => 'La cabaña especificada no existe',
            'cabins.*.prices.required_with' => 'Debe especificar al menos un precio por cabaña',
            'cabins.*.prices.*.num_guests.required_with' => 'La cantidad de huéspedes es obligatoria',
            'cabins.*.prices.*.num_guests.min' => 'La cantidad mínima de huéspedes es 2',
            'cabins.*.prices.*.num_guests.max' => 'La cantidad máxima de huéspedes es 255',
            'cabins.*.prices.*.price_per_night.required_with' => 'El precio por noche es obligatorio',
            'cabins.*.prices.*.price_per_night.min' => 'El precio por noche debe ser mayor o igual a 0',
            'date_ranges.*.start_date.required_with' => 'La fecha de inicio es obligatoria',
            'date_ranges.*.end_date.required_with' => 'La fecha de fin es obligatoria',
            'date_ranges.*.end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
        ];
    }
}
