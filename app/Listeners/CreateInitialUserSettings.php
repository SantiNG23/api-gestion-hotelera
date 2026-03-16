<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Models\User;
use App\Models\UserSetting;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateInitialUserSettings implements ShouldQueue
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

            UserSetting::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'tenant_id' => $event->tenantId,
                    'locale' => 'es_AR',
                    'timezone' => 'America/Argentina/Buenos_Aires',
                    'marketing_emails' => false,
                    'transactional_emails' => true,
                ]
            );
        });
    }
}
