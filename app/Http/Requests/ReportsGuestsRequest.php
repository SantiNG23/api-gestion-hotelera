<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ReportsGuestsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
