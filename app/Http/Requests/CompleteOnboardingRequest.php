<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

final class CompleteOnboardingRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'tenant' => ['required', 'array'],
            'tenant.name' => ['required', 'string', 'max:255'],
            'tenant.slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'tenant.tenant_id' => ['prohibited'],
            'tenant.email' => ['prohibited'],
            'tenant.role' => ['prohibited'],
            'tenant.is_admin' => ['prohibited'],
            'tenant.is_owner' => ['prohibited'],
            'user' => ['required', 'array'],
            'user.name' => ['required', 'string', 'max:255'],
            'user.password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'user.email' => ['prohibited'],
            'user.tenant_id' => ['prohibited'],
            'user.role' => ['prohibited'],
            'user.is_admin' => ['prohibited'],
            'user.is_owner' => ['prohibited'],
            'email' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'role' => ['prohibited'],
            'is_admin' => ['prohibited'],
            'is_owner' => ['prohibited'],
        ];
    }

    protected function sanitizeInput(): void
    {
        $input = $this->sanitizeValue($this->all());

        if (isset($input['tenant']['slug']) && is_string($input['tenant']['slug'])) {
            $input['tenant']['slug'] = mb_strtolower($input['tenant']['slug']);
        }

        $this->replace($input);
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->sanitizeValue($item);
            }

            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }

    public function messages(): array
    {
        return [
            'token.required' => 'El token es obligatorio.',
            'token.string' => 'El token debe ser una cadena valida.',
            'tenant.required' => 'Los datos del tenant son obligatorios.',
            'tenant.array' => 'Los datos del tenant son invalidos.',
            'tenant.name.required' => 'El nombre del tenant es obligatorio.',
            'tenant.name.max' => 'El nombre del tenant no puede tener mas de 255 caracteres.',
            'tenant.slug.required' => 'El slug del tenant es obligatorio.',
            'tenant.slug.max' => 'El slug del tenant no puede tener mas de 255 caracteres.',
            'tenant.slug.regex' => 'El slug del tenant debe usar solo minusculas, numeros y guiones medios.',
            'user.required' => 'Los datos del usuario son obligatorios.',
            'user.array' => 'Los datos del usuario son invalidos.',
            'user.name.required' => 'El nombre del usuario es obligatorio.',
            'user.name.max' => 'El nombre del usuario no puede tener mas de 255 caracteres.',
            'user.password.required' => 'La contrasena es obligatoria.',
            'user.password.confirmed' => 'La confirmacion de la contrasena no coincide.',
            'user.password.min' => 'La contrasena debe tener al menos 8 caracteres.',
            'user.password.mixed' => 'La contrasena debe contener al menos una mayuscula y una minuscula.',
            'user.password.mixed_case' => 'La contrasena debe contener al menos una mayuscula y una minuscula.',
            'user.password.numbers' => 'La contrasena debe contener al menos un numero.',
            'user.password.symbols' => 'La contrasena debe contener al menos un caracter especial.',
            'email.prohibited' => 'El email no puede enviarse en onboarding.',
            'tenant_id.prohibited' => 'El tenant_id no puede enviarse en onboarding.',
            'role.prohibited' => 'El role no puede enviarse en onboarding.',
            'is_admin.prohibited' => 'is_admin no puede enviarse en onboarding.',
            'is_owner.prohibited' => 'is_owner no puede enviarse en onboarding.',
            'tenant.tenant_id.prohibited' => 'El tenant_id no puede enviarse en onboarding.',
            'tenant.email.prohibited' => 'El email no puede enviarse dentro del tenant.',
            'tenant.role.prohibited' => 'El role no puede enviarse dentro del tenant.',
            'tenant.is_admin.prohibited' => 'is_admin no puede enviarse dentro del tenant.',
            'tenant.is_owner.prohibited' => 'is_owner no puede enviarse dentro del tenant.',
            'user.email.prohibited' => 'El email no puede enviarse dentro del usuario.',
            'user.tenant_id.prohibited' => 'El tenant_id no puede enviarse dentro del usuario.',
            'user.role.prohibited' => 'El role no puede enviarse dentro del usuario.',
            'user.is_admin.prohibited' => 'is_admin no puede enviarse dentro del usuario.',
            'user.is_owner.prohibited' => 'is_owner no puede enviarse dentro del usuario.',
        ];
    }
}
