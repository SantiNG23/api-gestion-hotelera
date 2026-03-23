<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\OnboardingInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingInvitationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_casts_timestamps_and_meta_correctly(): void
    {
        $invitation = OnboardingInvitation::factory()->create([
            'meta' => ['source' => 'backoffice'],
        ]);

        $invitation = $invitation->fresh();

        $this->assertInstanceOf(CarbonImmutable::class, $invitation->expires_at->toImmutable());
        $this->assertIsArray($invitation->meta);
        $this->assertSame('backoffice', $invitation->meta['source']);
        $this->assertDatabaseHas('onboarding_invitations', [
            'id' => $invitation->id,
            'email' => $invitation->email,
        ]);
        $this->assertDatabaseMissing('onboarding_invitations', [
            'id' => $invitation->id,
            'token_hash' => 'plain-token',
        ]);
    }

    #[Test]
    public function it_derives_pending_status_from_timestamps(): void
    {
        $invitation = OnboardingInvitation::factory()->pending()->make();

        $this->assertSame(OnboardingInvitation::STATUS_PENDING, $invitation->status);
        $this->assertTrue($invitation->isPending());
    }

    #[Test]
    public function it_derives_consumed_status_from_timestamps(): void
    {
        $invitation = OnboardingInvitation::factory()->consumed()->make();

        $this->assertSame(OnboardingInvitation::STATUS_CONSUMED, $invitation->status);
        $this->assertTrue($invitation->isConsumed());
    }

    #[Test]
    public function it_derives_expired_status_from_timestamps(): void
    {
        $invitation = OnboardingInvitation::factory()->expired()->make();

        $this->assertSame(OnboardingInvitation::STATUS_EXPIRED, $invitation->status);
        $this->assertTrue($invitation->isExpired());
    }

    #[Test]
    public function it_derives_revoked_status_from_timestamps(): void
    {
        $invitation = OnboardingInvitation::factory()->revoked()->make();

        $this->assertSame(OnboardingInvitation::STATUS_REVOKED, $invitation->status);
        $this->assertTrue($invitation->isRevoked());
    }

    #[Test]
    public function it_prioritizes_terminal_statuses_in_a_stable_order(): void
    {
        $revokedInvitation = OnboardingInvitation::factory()->make([
            'expires_at' => now()->subMinute(),
            'consumed_at' => now()->subMinutes(2),
            'revoked_at' => now()->subMinutes(3),
        ]);

        $consumedInvitation = OnboardingInvitation::factory()->make([
            'expires_at' => now()->subMinute(),
            'consumed_at' => now()->subMinutes(2),
            'revoked_at' => null,
        ]);

        $this->assertSame(OnboardingInvitation::STATUS_REVOKED, $revokedInvitation->status);
        $this->assertSame(OnboardingInvitation::STATUS_CONSUMED, $consumedInvitation->status);
    }
}
