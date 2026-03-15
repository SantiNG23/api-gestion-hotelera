<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

class AuthRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        $rules = [
            'email' => 'required|email|max:255',
            'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]+$/',
            'tenant_id' => ['prohibited'],
            'tenant_slug' => ['sometimes', 'string', Rule::exists('tenants', 'slug')->where('is_active', true)],
        ];

        $existingUserQuery = User::query()->where('email', $this->string('email')->toString());

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
            'tenant_id.prohibited' => 'El tenant_id no puede enviarse en este flujo de autenticación.',
            'tenant_slug.exists' => 'El tenant_slug debe corresponder a un tenant activo.',
        ];
    }
}
