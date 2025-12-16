# Guía de Datos de Prueba para Precios y Reservas

## Resumen

El `DemoDataSeeder` ahora incluye un módulo completo de **Pricing** que genera:

- ✅ **6 grupos de precios** con tarifas realistas
- ✅ **Múltiples rangos de precios** que cubren todo el año
- ✅ **Grupo de precio por defecto** para días sin rango específico

## Grupos de Precios Creados

| Grupo | Precio/Noche | Prioridad | Por Defecto |
|-------|--------------|-----------|-------------|
| Temporada Baja | $80.00 | 1 | ❌ |
| Temporada Media | $120.00 | 2 | ❌ |
| Temporada Alta | $180.00 | 3 | ❌ |
| Fin de Semana Largo | $200.00 | 4 | ❌ |
| Fiestas y Vacaciones | $300.00 | 5 | ❌ |
| **Tarifa por Defecto** | **$100.00** | **0** | **✅** |

## Rangos de Precios

Se generan automáticamente basados en la fecha actual:

### Calendario del Año

- **Enero-Marzo**: Temporada Media ($120/noche)
- **Abril-Junio**: Temporada Baja ($80/noche)
- **Julio-Agosto**: Temporada Media ($120/noche)
- **Septiembre-Noviembre**: Temporada Alta ($180/noche)
- **Diciembre**: Fiestas y Vacaciones ($300/noche)

### Rangos Futuros (desde hoy)

- **Próximos 1-3 meses**: Temporada Media ($120/noche)
- **Próximos 7-10 días**: Fin de Semana Largo ($200/noche)

## Ejemplos de Reservas para Pruebas

### Ejemplo 1: Reserva en Temporada Baja (3 noches)

```txt
Check-in: 15 de mayo
Check-out: 18 de mayo
Cabaña: cualquiera
Total: $80 x 3 = $240
Seña (50%): $120
Saldo (50%): $120
```

### Ejemplo 2: Reserva en Fiestas (5 noches)

```txt
Check-in: 20 de diciembre
Check-out: 25 de diciembre
Cabaña: cualquiera
Total: $300 x 5 = $1,500
Seña (50%): $750
Saldo (50%): $750
```

### Ejemplo 3: Reserva Mixta (abarca múltiples tarifas - 4 noches)

```txt
Check-in: 30 de agosto (Temporada Media: $120)
Check-out: 3 de septiembre (Temporada Alta: $180)

Desglose:
- 30 ago: $120
- 31 ago: $120
- 1 sep: $180
- 2 sep: $180
Total: $600
Seña (50%): $300
Saldo (50%): $300
```

### Ejemplo 4: Reserva en Fin de Semana Largo

```txt
Check-in: 7 días desde hoy
Check-out: 11 días desde hoy
Cabaña: cualquiera
Total: $200 x 4 = $800
Seña (50%): $400
Saldo (50%): $400
```

## Cómo Ejecutar el Seeder

### Primera vez (crear datos)

```bash
php artisan db:seed --class=DemoDataSeeder
```

### Resetear todo (incluyendo precios)

```bash
php artisan migrate:fresh --seed --class=DemoDataSeeder
```

### Solo resetear el seeder sin migrar

```bash
php artisan db:seed --class=DemoDataSeeder
```

## Cómo Usar en Tests

### Para hacer una cotización (preview de precio)

```php
$checkIn = Carbon::parse('2025-05-15');
$checkOut = Carbon::parse('2025-05-18');

$quote = app(PriceCalculatorService::class)->generateQuote(
    cabinId: 1,
    checkIn: $checkIn->toDateString(),
    checkOut: $checkOut->toDateString()
);

// Resultado:
// {
//   "cabin_id": 1,
//   "check_in": "2025-05-15",
//   "check_out": "2025-05-18",
//   "total": 240.00,
//   "deposit": 120.00,
//   "balance": 120.00,
//   "nights": 3,
//   "breakdown": [
//     { "date": "2025-05-15", "price": 80, "price_group": "Temporada Baja" },
//     { "date": "2025-05-16", "price": 80, "price_group": "Temporada Baja" },
//     { "date": "2025-05-17", "price": 80, "price_group": "Temporada Baja" }
//   ]
// }
```

### Para crear una reserva con precio calculado automáticamente

```php
$reservation = app(ReservationService::class)->createReservation([
    'cabin_id' => 1,
    'check_in_date' => '2025-05-15',
    'check_out_date' => '2025-05-18',
    'client' => [
        'name' => 'Juan Pérez',
        'email' => 'juan@example.com',
        'phone' => '+54 9 11 1234-5678',
    ],
]);

// La reserva tendrá:
// - total_price: $240.00
// - deposit_amount: $120.00
// - balance_amount: $120.00
// - status: pending_confirmation
```

## Datos de Prueba Disponibles

Además de los precios, el seeder también crea:

### Tenants

- 1 tenant: "Demo Tenant"

### Usuarios

- 1 admin: `admin@example.com` / `Admin123!`
- 1 test user: `test@example.com`

### Clientes

- 13 clientes: 11 activos + 2 eliminados

### Cabañas

- 16 cabañas: 9 activas + 4 inactivas + 3 eliminadas
- Ejemplos: "Cabaña del Bosque", "Cabaña del Lago", "Cabaña de Lujo"

### Amenidades (Features)

- 16 amenidades: 13 activas + 2 inactivas + 1 eliminada
- Ejemplos: Piscina, WiFi, Aire Acondicionado, Parrilla

### Relaciones Cabaña-Amenidad

- Cada cabaña tiene 2-5 amenidades vinculadas

## Notas Importantes

1. **Los rangos de precios son dinámicos**: Se calculan en base a la fecha actual, por lo que los valores siempre serán relevantes
2. **Grupo por defecto**: Si una fecha no coincide con ningún rango específico, se usa la tarifa por defecto ($100/noche)
3. **Desglose de precios**: El servicio `PriceCalculatorService` genera un desglose completo de precios por noche, mostrando qué grupo se aplicó a cada fecha
4. **Seña y Saldo**: El sistema calcula automáticamente:
   - **Seña**: 50% del total
   - **Saldo**: 50% del total

5. **Para renovar datos**: Si necesitas resetear los datos de prueba, usa:

```bash
php artisan migrate:fresh --seed
```

## Solución de Problemas

### ¿Por qué la cotización muestra $0?

- Verifica que la fecha de check-in sea ANTES de check-out
- Verifica que haya al menos 1 noche entre check-in y check-out

### ¿Por qué no se aplica el grupo de precio que espero?

- Verifica que el rango de precios cubra las fechas de la reserva
- Usa `carbon()->toDateString()` para formatear fechas correctamente
- Recuerda que solo se buscan rangos activos (sin soft delete)

### ¿Cómo filtro por grupo de precio específico?

```php
$lowSeasonReservations = Reservation::query()
    ->whereIn('total_price', function($query) {
        return $query->selectRaw('80 * (check_out_date - check_in_date)')
                    ->from('reservations');
    })
    ->get();
```
