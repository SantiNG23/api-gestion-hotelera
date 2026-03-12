<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class MissingTenantContextException extends RuntimeException
{
    public static function forOperation(): self
    {
        return new self('Falta contexto tenant activo para ejecutar una operacion tenant-scoped.');
    }

    public static function forCommand(): self
    {
        return new self('Falta contexto tenant activo. Indica un tenant explicito para este comando.');
    }
}
