<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Models\UserSetting;
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
        UserSetting::query()->firstOrCreate(
            ['user_id' => $event->user->id],
            [
                'tenant_id' => $event->user->tenant_id,
                'locale' => 'es_AR',
                'timezone' => 'America/Argentina/Buenos_Aires',
                'marketing_emails' => false,
                'transactional_emails' => true,
            ]
        );
    }
}
