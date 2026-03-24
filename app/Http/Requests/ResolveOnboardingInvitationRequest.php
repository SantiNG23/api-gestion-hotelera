<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class ResolveOnboardingInvitationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'tenant_id' => ['prohibited'],
            'email' => ['prohibited'],
            'role' => ['prohibited'],
            'is_admin' => ['prohibited'],
            'is_owner' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'El token es obligatorio.',
            'token.string' => 'El token debe ser una cadena valida.',
            'token.max' => 'El token no puede tener mas de 255 caracteres.',
            'tenant_id.prohibited' => 'El tenant_id no puede enviarse en onboarding.',
            'email.prohibited' => 'El email no puede enviarse en onboarding.',
            'role.prohibited' => 'El role no puede enviarse en onboarding.',
            'is_admin.prohibited' => 'is_admin no puede enviarse en onboarding.',
            'is_owner.prohibited' => 'is_owner no puede enviarse en onboarding.',
        ];
    }
}
