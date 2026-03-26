<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Middleware\AuthenticatePublicQuoteTenant;
use App\Models\Tenant;
use Illuminate\Validation\Rule;

class PublicQuoteRequest extends ApiRequest
{
    public function rules(): array
    {
        $tenant = $this->publicTenant();

        return [
            'cabin_id' => [
                'required',
                'integer',
                Rule::exists('cabins', 'id')->where('tenant_id', $tenant->id),
            ],
            'check_in_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'date_format:Y-m-d', 'after:check_in_date'],
            'num_guests' => ['required', 'integer', 'min:2', 'max:255'],
            'reservation_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'cabin_id.required' => 'La cabaña es obligatoria',
            'cabin_id.exists' => 'La cabaña no existe o no pertenece al tenant indicado',
            'check_in_date.required' => 'La fecha de check-in es obligatoria',
            'check_in_date.date_format' => 'La fecha de check-in debe tener el formato YYYY-MM-DD',
            'check_in_date.after_or_equal' => 'La fecha de check-in debe ser hoy o posterior',
            'check_out_date.required' => 'La fecha de check-out es obligatoria',
            'check_out_date.date_format' => 'La fecha de check-out debe tener el formato YYYY-MM-DD',
            'check_out_date.after' => 'La fecha de check-out debe ser posterior al check-in',
            'reservation_id.prohibited' => 'El endpoint público no acepta reservation_id',
        ];
    }

    public function publicTenant(): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $this->attributes->get(AuthenticatePublicQuoteTenant::TENANT_ATTRIBUTE);

        return $tenant;
    }
}
