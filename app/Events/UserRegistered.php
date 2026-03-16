<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

class UserRegistered
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $userId,
        public readonly int $tenantId,
    ) {}
}
