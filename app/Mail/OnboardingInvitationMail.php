<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\OnboardingInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly OnboardingInvitation $invitation,
        public readonly string $plainTextToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Completa tu onboarding en Mirador de Luz',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-invitation',
            with: [
                'email' => $this->invitation->email,
                'expiresAt' => $this->invitation->expires_at,
                'invitationUrl' => $this->buildInvitationUrl(),
                'tenantNamePrefill' => $this->invitation->tenant_name_prefill,
            ],
        );
    }

    private function buildInvitationUrl(): string
    {
        $baseUrl = (string) config('onboarding.frontend_url');
        $parts = parse_url($baseUrl);
        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['token'] = $this->plainTextToken;
        $builtUrl = '';

        if (isset($parts['scheme'])) {
            $builtUrl .= $parts['scheme'].'://';
        }

        if (isset($parts['user'])) {
            $builtUrl .= $parts['user'];

            if (isset($parts['pass'])) {
                $builtUrl .= ':'.$parts['pass'];
            }

            $builtUrl .= '@';
        }

        if (isset($parts['host'])) {
            $builtUrl .= $parts['host'];
        }

        if (isset($parts['port'])) {
            $builtUrl .= ':'.$parts['port'];
        }

        $builtUrl .= $parts['path'] ?? '';

        $queryString = http_build_query($query);

        if ($queryString !== '') {
            $builtUrl .= '?'.$queryString;
        }

        if (isset($parts['fragment'])) {
            $builtUrl .= '#'.$parts['fragment'];
        }

        return $builtUrl;
    }
}
