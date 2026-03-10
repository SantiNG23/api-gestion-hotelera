<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;

class AuthRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->input('tenant_id') ?? $this->user()?->tenant_id;

        $rules = [
            'email' => 'required|email|max:255',
            'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]+$/',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
        ];

        $existingUserQuery = User::query()->where('email', $this->email);

        if ($tenantId !== null) {
            $existingUserQuery->where('tenant_id', $tenantId);
        }

        // Si el email no existe, aplicamos las reglas de registro
        if (! $existingUserQuery->exists()) {
            $rules = array_merge($rules, [
                'name' => 'required|string|min:3|max:255|regex:/^[\p{L}\s]+$/u',
                'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]+$/|confirmed',
            ]);
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
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'email.max' => 'El correo electrónico no puede tener más de 255 caracteres.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe contener al menos una letra mayúscula, una minúscula, un número y un carácter especial (@$!%*?&.).',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}
