<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;

class ClientRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $clientId = $this->route('client');
        $tenantId = Auth::user()?->tenant_id;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        $rules = [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'dni' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:20',
            ],
            'age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ];

        // Validación única de DNI por tenant
        if ($this->isMethod('POST')) {
            $rules['dni'][] = "unique:clients,dni,NULL,id,tenant_id,{$tenantId}";
        } elseif ($isUpdate && $this->has('dni')) {
            $rules['dni'][] = "unique:clients,dni,{$clientId},id,tenant_id,{$tenantId}";
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
            'dni.required' => 'El DNI es obligatorio',
            'dni.unique' => 'Ya existe un cliente con este DNI',
            'age.integer' => 'La edad debe ser un número entero',
            'age.min' => 'La edad no puede ser negativa',
            'age.max' => 'La edad no es válida',
            'email.email' => 'El email no tiene un formato válido',
        ];
    }
}
