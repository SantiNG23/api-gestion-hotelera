<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsoleScheduleTest extends TestCase
{
    #[Test]
    public function it_schedules_expired_reservations_cancellation_with_explicit_admin_mode(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertTrue(collect($events)->contains(function (object $event): bool {
            return str_contains($event->command, 'reservations:cancel-expired --all-tenants');
        }));
    }
}
