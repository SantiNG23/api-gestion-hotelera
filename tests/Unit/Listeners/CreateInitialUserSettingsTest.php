<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\UserRegistered;
use App\Listeners\CreateInitialUserSettings;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateInitialUserSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_initial_settings_for_registered_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $event = new UserRegistered($user);

        $listener = new CreateInitialUserSettings;
        $listener->handle($event);

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'locale' => 'es_AR',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'marketing_emails' => false,
            'transactional_emails' => true,
        ]);
    }

    #[Test]
    public function it_does_not_duplicate_settings_on_second_handle(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $event = new UserRegistered($user);

        $listener = new CreateInitialUserSettings;
        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('user_settings', 1);
        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }
}
