<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class AuthDiscoverRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'tenant_id' => ['prohibited'],
        ];
    }

    protected function sanitizeInput(): void
    {
        parent::sanitizeInput();

        if (is_string($this->input('email'))) {
            $this->merge([
                'email' => mb_strtolower($this->string('email')->toString()),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El correo electronico es obligatorio.',
            'email.email' => 'El correo electronico debe ser valido.',
            'email.max' => 'El correo electronico no puede tener mas de 255 caracteres.',
            'tenant_id.prohibited' => 'El tenant_id no puede enviarse en este flujo de autenticacion.',
        ];
    }
}
