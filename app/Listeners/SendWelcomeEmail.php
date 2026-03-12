<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\WelcomeUserMail;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        app(TenantContext::class)->run($event->tenantId, function () use ($event): void {
            $user = User::query()
                ->whereKey($event->userId)
                ->where('tenant_id', $event->tenantId)
                ->firstOrFail();

            Mail::to($user)->queue(new WelcomeUserMail($user));
        });
    }
}
