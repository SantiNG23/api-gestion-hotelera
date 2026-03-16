<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\UserRegistered;
use App\Listeners\SendWelcomeEmail;
use App\Mail\WelcomeUserMail;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendWelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_queues_welcome_email_for_registered_user(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'nuevo@miradordeluz.com',
        ]);
        $this->setTenantContext(null);
        $event = new UserRegistered($user->id, $tenant->id);

        $listener = new SendWelcomeEmail;
        $listener->handle($event);

        Mail::assertQueued(WelcomeUserMail::class, function (WelcomeUserMail $mail) use ($user): bool {
            return $mail->user->is($user) && $mail->hasTo($user->email);
        });
        $this->assertNull(app(TenantContext::class)->id());
    }
}
