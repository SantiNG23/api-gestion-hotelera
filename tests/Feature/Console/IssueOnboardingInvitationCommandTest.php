<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Mail\OnboardingInvitationMail;
use App\Models\OnboardingInvitation;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IssueOnboardingInvitationCommandTest extends TestCase
{
    #[Test]
    public function it_persists_a_pending_invitation_and_dispatches_the_email(): void
    {
        config()->set('onboarding.frontend_url', 'https://frontend.test/onboarding/bootstrap');
        config()->set('onboarding.invitation.expires_in_hours', 48);

        $this->artisan('onboarding:issue-invitation', [
            'email' => 'owner@example.com',
            '--tenant-name' => 'Hotel Demo',
            '--tenant-slug' => 'Hotel Demo Norte',
        ])->assertSuccessful();

        $invitation = OnboardingInvitation::query()->sole();

        $this->assertSame(OnboardingInvitation::STATUS_PENDING, $invitation->status);
        $this->assertSame('owner@example.com', $invitation->email);
        $this->assertSame('Hotel Demo', $invitation->tenant_name_prefill);
        $this->assertSame('hotel-demo-norte', $invitation->tenant_slug_prefill);
        $this->assertTrue($invitation->expires_at->between(now()->addHours(47), now()->addHours(49)));

        Mail::assertSent(OnboardingInvitationMail::class, function (OnboardingInvitationMail $mail) use ($invitation): bool {
            $this->assertTrue($mail->hasTo('owner@example.com'));
            $this->assertTrue($mail->invitation->is($invitation));
            $this->assertNotSame($mail->plainTextToken, $invitation->token_hash);
            $this->assertStringStartsWith('btp_live_', $mail->plainTextToken);

            return true;
        });
    }
}
