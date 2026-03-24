<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Onboarding\IssueOnboardingInvitationService;
use Illuminate\Console\Command;

class IssueOnboardingInvitationCommand extends Command
{
    protected $signature = 'onboarding:issue-invitation
        {email : Correo destinatario de la invitacion}
        {--tenant-name= : Nombre sugerido del tenant}
        {--tenant-slug= : Slug sugerido del tenant}';

    protected $description = 'Emite una invitacion operativa de onboarding';

    public function __construct(
        private readonly IssueOnboardingInvitationService $issueOnboardingInvitationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $invitation = $this->issueOnboardingInvitationService->issue(
            email: (string) $this->argument('email'),
            tenantNamePrefill: $this->option('tenant-name') ?: null,
            tenantSlugPrefill: $this->option('tenant-slug') ?: null,
        );

        $this->info(sprintf(
            'Invitacion emitida para %s. ID: %d. Expira: %s',
            $invitation->email,
            $invitation->id,
            $invitation->expires_at->toIso8601String(),
        ));

        return self::SUCCESS;
    }
}
