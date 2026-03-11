<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\UserRegistered;
use App\Listeners\SendWelcomeEmail;
use App\Mail\WelcomeUserMail;
use App\Models\User;
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

        $user = User::factory()->create([
            'email' => 'nuevo@miradordeluz.com',
        ]);
        $event = new UserRegistered($user);

        $listener = new SendWelcomeEmail;
        $listener->handle($event);

        Mail::assertQueued(WelcomeUserMail::class, function (WelcomeUserMail $mail) use ($user): bool {
            return $mail->user->is($user) && $mail->hasTo($user->email);
        });
    }
}
