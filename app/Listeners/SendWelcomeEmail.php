<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\WelcomeUserMail;
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
        // TODO(MVP+): reemplazar este welcome email por flujo de invitacion segura
        // con magic link de primer acceso + definicion de contraseña para cuentas internas.
        Mail::to($event->user)->queue(new WelcomeUserMail($event->user));
    }
}
