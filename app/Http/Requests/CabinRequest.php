<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CabinRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $isPost = $this->isMethod('POST');
        $tenantId = Auth::user()?->tenant_id;
        $featureExistsRule = Rule::exists('features', 'id');

        if ($tenantId !== null) {
            $featureExistsRule = $featureExistsRule->where('tenant_id', $tenantId);
        }

        $rules = [
            'name' => ['string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['integer', 'min:1', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'feature_ids' => ['sometimes', 'array'],
            'feature_ids.*' => ['integer', $featureExistsRule],
        ];

        if ($isPost) {
            array_unshift($rules['name'], 'required');
            array_unshift($rules['capacity'], 'required');
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
            'name.required' => 'El nombre es obligatorio',
            'capacity.required' => 'La capacidad es obligatoria',
            'capacity.min' => 'La capacidad mínima es 1 persona',
            'capacity.max' => 'La capacidad máxima es 50 personas',
            'feature_ids.array' => 'Las características deben ser un listado',
            'feature_ids.*.exists' => 'Una de las características seleccionadas no existe o no pertenece a tu organización',
        ];
    }
}
