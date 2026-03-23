<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitacion de onboarding</title>
</head>
<body>
    <h1>Tu invitacion para Mirador de Luz</h1>

    <p>Recibiste una invitacion para completar el onboarding de tu cuenta.</p>

    @if ($tenantNamePrefill)
        <p>Tenant sugerido: {{ $tenantNamePrefill }}</p>
    @endif

    <p>Correo invitado: {{ $email }}</p>
    <p>La invitacion vence el {{ $expiresAt->utc()->format('d/m/Y H:i') }} UTC.</p>
    <p>
        <a href="{{ $invitationUrl }}">Completar onboarding</a>
    </p>
</body>
</html>
