<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\OnboardingInvitationMail;
use App\Models\OnboardingInvitation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingInvitationMailTest extends TestCase
{
    #[Test]
    public function it_builds_the_frontend_link_with_the_token_query_param(): void
    {
        config()->set('onboarding.frontend_url', 'https://frontend.test/onboarding/bootstrap');

        $invitation = OnboardingInvitation::factory()->make([
            'email' => 'owner@example.com',
            'expires_at' => now()->addHours(72),
            'tenant_name_prefill' => 'Hotel Demo',
        ]);

        $mail = new OnboardingInvitationMail($invitation, 'btp_live_test_token');

        $mail->assertHasSubject('Completa tu onboarding en Mirador de Luz');
        $mail->assertSeeInHtml('https://frontend.test/onboarding/bootstrap?token=btp_live_test_token');
        $mail->assertSeeInHtml('La invitacion vence el');
        $mail->assertDontSeeInHtml('password');
    }

    #[Test]
    public function it_appends_the_token_to_existing_query_parameters(): void
    {
        config()->set('onboarding.frontend_url', 'https://frontend.test/onboarding/bootstrap?source=ops');

        $mail = new OnboardingInvitationMail(OnboardingInvitation::factory()->make(), 'btp_live_test_token');

        $mail->assertSeeInHtml('https://frontend.test/onboarding/bootstrap?source=ops&token=btp_live_test_token');
    }
}
