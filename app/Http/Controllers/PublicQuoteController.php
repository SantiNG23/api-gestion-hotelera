<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PublicQuoteRequest;
use App\Services\PublicQuote\PublicQuoteService;
use Illuminate\Http\JsonResponse;

class PublicQuoteController extends Controller
{
    public function __construct(
        private readonly PublicQuoteService $publicQuoteService,
    ) {}

    public function store(PublicQuoteRequest $request): JsonResponse
    {
        return $this->successResponse(
            $this->publicQuoteService->quote(
                $request->publicTenant(),
                (int) $request->integer('cabin_id'),
                (string) $request->string('check_in_date'),
                (string) $request->string('check_out_date'),
                (int) $request->integer('num_guests'),
            )
        );
    }
}
