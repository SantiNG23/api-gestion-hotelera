<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OnboardingException;
use App\Http\Requests\CompleteOnboardingRequest;
use App\Http\Requests\ResolveOnboardingInvitationRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\OnboardingInvitationResource;
use App\Services\Onboarding\CompleteOnboardingService;
use App\Services\Onboarding\ResolveOnboardingInvitationService;
use Illuminate\Http\JsonResponse;
use Throwable;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly ResolveOnboardingInvitationService $resolveOnboardingInvitationService,
        private readonly CompleteOnboardingService $completeOnboardingService,
    ) {}

    public function resolve(ResolveOnboardingInvitationRequest $request): JsonResponse
    {
        try {
            $invitation = $this->resolveOnboardingInvitationService->resolve($request->validated('token'));

            return $this->successResponse(
                new OnboardingInvitationResource($invitation),
                'Invitacion valida.'
            );
        } catch (OnboardingException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->status(), $exception->errors());
        }
    }

    public function complete(CompleteOnboardingRequest $request): JsonResponse
    {
        try {
            $payload = $this->completeOnboardingService->complete($request->validated());

            return $this->successResponse(
                new AuthResource($payload),
                'Onboarding completado exitosamente.',
                201
            );
        } catch (OnboardingException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->status(), $exception->errors());
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse(
                'El onboarding no esta disponible temporalmente.',
                503,
                ['code' => ['onboarding_unavailable']]
            );
        }
    }
}
