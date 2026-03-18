<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class AuthLoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'tenant_slug' => ['required', 'string', 'max:255'],
            'tenant_id' => ['prohibited'],
        ];
    }

    protected function sanitizeInput(): void
    {
        parent::sanitizeInput();

        $payload = [];

        if (is_string($this->input('email'))) {
            $payload['email'] = mb_strtolower($this->string('email')->toString());
        }

        if (is_string($this->input('tenant_slug'))) {
            $payload['tenant_slug'] = mb_strtolower($this->string('tenant_slug')->toString());
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El correo electronico es obligatorio.',
            'email.email' => 'El correo electronico debe ser valido.',
            'email.max' => 'El correo electronico no puede tener mas de 255 caracteres.',
            'password.required' => 'La contrasena es obligatoria.',
            'tenant_slug.required' => 'Selecciona una cuenta para continuar.',
            'tenant_slug.string' => 'Selecciona una cuenta para continuar.',
            'tenant_slug.max' => 'El tenant_slug no puede tener mas de 255 caracteres.',
            'tenant_id.prohibited' => 'El tenant_id no puede enviarse en este flujo de autenticacion.',
        ];
    }
}
