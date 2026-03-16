<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $tenantId = Auth::user()?->tenant_id;

        // Reglas comunes para todas las peticiones
        $rules = [
            'name' => [
                'string',
                'min:3',
                'max:255',
                'regex:/^[\p{L}\s]+$/u',
            ],
            'email' => [
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore(Auth::id())
                    ->where('tenant_id', $tenantId),
            ],
        ];

        // Si es una actualización de contraseña
        if ($this->isMethod('put') && $this->route()->getName() === 'users.password.update') {
            return [
                'current_password' => 'required|current_password',
                'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]+$/|confirmed',
            ];
        }

        // Si es una actualización de perfil
        if ($this->isMethod('put') && $this->route()->getName() === 'users.profile.update') {
            array_unshift($rules['name'], 'required');
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
            'name.required' => 'El nombre es obligatorio.',
            'name.min' => 'El nombre debe tener al menos 3 caracteres.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'email.max' => 'El correo electrónico no puede tener más de 255 caracteres.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'current_password.required' => 'La contraseña actual es obligatoria.',
            'current_password.current_password' => 'La contraseña actual es incorrecta.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe contener al menos una letra mayúscula, una minúscula, un número y un carácter especial (@$!%*?&.).',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}
