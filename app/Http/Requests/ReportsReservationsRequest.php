<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReportsReservationsRequest extends ApiRequest
{
    public function rules(): array
    {
        $tenantId = Auth::user()?->tenant_id;
        $cabinExistsRule = Rule::exists('cabins', 'id');

        if ($tenantId !== null) {
            $cabinExistsRule = $cabinExistsRule->where('tenant_id', $tenantId);
        }

        return [
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in([
                Reservation::STATUS_PENDING_CONFIRMATION,
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_FINISHED,
                Reservation::STATUS_CANCELLED,
            ])],
            'cabin_id' => ['nullable', 'integer', $cabinExistsRule],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
