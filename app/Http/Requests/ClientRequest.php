<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
        $isPost = $this->isMethod('POST');

        $rules = [
            'name' => ['string', 'max:255'],
            'dni' => [
                'string',
                'max:20',
                Rule::notIn([Client::DNI_BLOCK]),
            ],
            'age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ];

        if ($isPost) {
            array_unshift($rules['name'], 'required');
            array_unshift($rules['dni'], 'required');
        }

        // Validación única de DNI por tenant
        if ($isPost) {
            $rules['dni'][] = Rule::unique('clients', 'dni')->where('tenant_id', $tenantId);
        } elseif ($isUpdate && $this->has('dni')) {
            $rules['dni'][] = Rule::unique('clients', 'dni')->ignore($clientId)->where('tenant_id', $tenantId);
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
            'dni.not_in' => 'El DNI tecnico de bloqueos no puede asignarse a un cliente',
            'age.integer' => 'La edad debe ser un número entero',
            'age.min' => 'La edad no puede ser negativa',
            'age.max' => 'La edad no es válida',
            'email.email' => 'El email no tiene un formato válido',
        ];
    }
}
