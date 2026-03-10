<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_user_can_register(): void
    {
        $userData = [
            'name' => 'Usuario Prueba',
            'email' => 'usuario@prueba.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/v1/auth', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'token',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'usuario@prueba.com',
            'name' => 'Usuario Prueba',
        ]);
    }

    #[Test]
    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario@prueba.com',
            'password' => bcrypt('Password123!'),
        ]);

        $loginData = [
            'email' => 'usuario@prueba.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/v1/auth', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'token',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    #[Test]
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Sesión cerrada exitosamente',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function test_user_can_get_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $response->assertJsonMissingPath('data.token');
    }

    #[Test]
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario@prueba.com',
            'password' => bcrypt('Password123!'),
        ]);

        $loginData = [
            'email' => 'usuario@prueba.com',
            'password' => 'WrongPassword123!',
        ];

        $response = $this->postJson('/api/v1/auth', $loginData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function test_user_cannot_register_with_invalid_email_and_password(): void
    {
        $userData = [
            'name' => 'Usuario Prueba',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '123',
        ];

        $response = $this->postJson('/api/v1/auth', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function test_user_cannot_register_with_invalid_name(): void
    {
        $userData = [
            'name' => 'AB', // Menos de 3 caracteres
            'email' => 'valido@email.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/v1/auth', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function test_user_cannot_register_with_unconfirmed_password(): void
    {
        $userData = [
            'name' => 'Usuario Prueba',
            'email' => 'valido@email.com',
            'password' => 'Password123!',
            'password_confirmation' => 'OtraPassword123!',
        ];

        $response = $this->postJson('/api/v1/auth', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function test_token_has_correct_format(): void
    {
        $userData = [
            'name' => 'Usuario Prueba',
            'email' => 'usuario@prueba.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/v1/auth', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'token',
                ],
            ]);

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertStringContainsString('|', $token);
    }

    #[Test]
    public function test_cannot_access_protected_routes_without_token(): void
    {
        $response = $this->getJson('/api/v1/auth');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'No autenticado',
            ]);
    }

    #[Test]
    public function test_cannot_access_with_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/v1/auth');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'No autenticado',
            ]);
    }

    #[Test]
    public function it_can_register_a_new_user()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/v1/auth', $userData);

        $this->assertApiResponse($response, 201);
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    #[Test]
    public function it_can_login_an_existing_user()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->postJson('/api/v1/auth', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $this->assertApiResponse($response);
        $this->assertNotNull($response->json('data.token'));
    }

    #[Test]
    public function it_can_logout_a_user()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth');

        $this->assertApiResponse($response);
        $this->assertCount(0, $user->fresh()->tokens);
    }

    #[Test]
    public function it_validates_required_fields_on_registration()
    {
        $response = $this->postJson('/api/v1/auth', []);

        $this->assertApiError($response, 422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    #[Test]
    public function it_validates_password_confirmation_on_registration()
    {
        $response = $this->postJson('/api/v1/auth', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $this->assertApiError($response, 422);
        $response->assertJsonValidationErrors(['password']);
    }

    // ============================================================================
    // TESTS PARA VALIDAR RIESGOS CRÍTICOS DE MULTITENANT
    // ============================================================================

    /**
     * RIESGO 11 - CRÍTICA: Email único global viola aislamiento de tenants
     *
     * Ubicación: database/migrations/0001_01_01_000000_create_users_table.php:19
     * Problema: El índice único en 'email' es simple, no compuesto con 'tenant_id'
     * En un sistema multi-tenant, esto significa que dos tenants NO pueden tener
     * usuarios con el mismo email, violando el aislamiento.
     *
     * Escenario esperado:
     * 1. Usuario juan@example.com del Tenant A crea una cuenta ✓
     * 2. Mismo usuario intenta registrarse en Tenant B con su email
     * 3. Debería permitirse (2 usuarios distintos en BD, aislados por tenant)
     * 4. Actualmente falla con error 422 (viola multitenant)
     */
    #[Test]
    public function test_email_unique_global_blocks_same_email_different_tenants(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #11 CRÍTICA - Email único global viola multitenant');

        // Tenant 1 crea usuario con juan@example.com
        $userData1 = [
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];
        $response1 = $this->postJson('/api/v1/auth', $userData1);
        $response1->assertStatus(201);

        // Extraer tenant_id del usuario creado
        $user1 = User::where('email', 'juan@example.com')->first();
        $tenant1Id = $user1->tenant_id;

        // Simular cambio a Tenant 2 (en un escenario real sería por subdominio/header)
        // Tenant 2 intenta crear usuario con mismo email
        $userData2 = [
            'name' => 'Juan García',
            'email' => 'juan@example.com', // Mismo email en diferente tenant
            'password' => 'AnotherPass123!',
            'password_confirmation' => 'AnotherPass123!',
        ];
        $response2 = $this->postJson('/api/v1/auth', $userData2);

        // ESPERADO: Debería permitirse (201) - 2 usuarios distintos con mismo email en tenants diferentes
        // ACTUAL: Falla con 422 Unprocessable Entity (constraint único simple)
        $this->assertEquals(201, $response2->getStatusCode(), 'RFC 14714: Mismo email en tenants diferentes debería permitirse');
        $response2->assertStatus(201);

        // Verificar que existan 2 usuarios distintos
        $usersWithEmail = User::where('email', 'juan@example.com')->get();
        $this->assertCount(2, $usersWithEmail, 'Debería haber 2 usuarios con email juan@example.com en tenants distintos');

        // Verificar que pertenecen a tenants diferentes
        $user2 = User::where('email', 'juan@example.com')
            ->where('name', 'Juan García')
            ->first();
        $this->assertNotNull($user2);
        $this->assertNotEquals($tenant1Id, $user2->tenant_id, 'Los usuarios deben pertenecer a tenants diferentes');
    }

    /**
     * RIESGO 12 - CRÍTICA: AuthService.authenticate() busca usuarios sin filtrado tenant_id
     *
     * Ubicación: app/Services/AuthService.php:18 y :44
     * Problema: Las búsquedas de usuario son globales sin filtrado por tenant_id
     * User model NO usa BelongsToTenant trait, entonces no hay scope automático
     *
     * Código problemático:
     *   $user = User::where('email', $email)->first();  // sin ::where('tenant_id', ...)
     *
     * Riesgo: Si hay usuarios con el mismo email en diferentes tenants,
     * authenticate() podría retornar el usuario del tenant incorrecto,
     * permitiendo login cruzado entre tenants.
     */
    #[Test]
    public function test_auth_service_finds_user_without_tenant_filtering(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #12 CRÍTICA - AuthService busca sin filtrado tenant_id');

        // Crear 2 usuarios con el mismo email pero en tenants diferentes
        $tenant1 = Tenant::firstOrCreate(['slug' => 'tenant-1'], ['name' => 'Tenant One']);
        $tenant2 = Tenant::firstOrCreate(['slug' => 'tenant-2'], ['name' => 'Tenant Two']);

        /** @var User $user1 */
        $user1 = User::factory()->create([
            'email' => 'shared@example.com',
            'password' => bcrypt('Password123!'),
            'tenant_id' => $tenant1->id,
        ]);

        /** @var User $user2 */
        $user2 = User::factory()->create([
            'email' => 'shared@example.com', // Mismo email (!!)
            'password' => bcrypt('DifferentPassword456!'),
            'tenant_id' => $tenant2->id,
        ]);

        // Simular login desde Tenant 1 con las credenciales de Usuario 1
        // El AuthService debería retornar user1, NO user2
        $this->actingAs($user1); // Contexto: Tenant 1
        $this->assertEquals($user1->tenant_id, Auth::user()->tenant_id);

        // ESPERADO: Búsqueda de User::where('email', 'shared@example.com')
        //           debería filtrar por tenant_id del usuario autenticado
        // ACTUAL: Retorna el "primer" usuario con ese email (indefinido en orden)
        //         Sin BelongsToTenant trait, no hay scope automático
        $foundUser = User::where('email', 'shared@example.com')->first();
        $this->assertNotNull($foundUser);

        // CRÍTICA: Sin scope de tenant, no podemos garantizar que sea user1
        $this->assertEquals(
            $user1->id,
            $foundUser->id,
            'Sin filtrado de tenant en AuthService, la búsqueda podría retornar usuario del tenant equivocado'
        );
    }

    /**
     * RIESGO 13 - ALTA: AuthRequest::rules() valida email sin contexto de tenant
     *
     * Ubicación: app/Http/Requests/AuthRequest.php:23
     * Problema: Validación de email duplicado decide login vs registro sin contexto de tenant
     * Código: if (! User::where('email', $this->email)->exists())
     *
     * Escenario de race condition:
     * 1. Tenant A: POST /auth con email nuevo@example.com → pasa validación
     * 2. Tenant B: simultáneamente POST /auth con nuevo@example.com → pasa validación
     * 3. Ambas requests crean usuario
     * 4. Violación de constraint único simple → error 500
     */
    #[Test]
    public function test_auth_request_validates_email_globally_without_tenant_context(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #13 ALTA - AuthRequest valida email globally');

        // Crear usuario en Tenant 1
        $tenant1 = Tenant::firstOrCreate(['slug' => 'tenant-1'], ['name' => 'Tenant One']);
        User::factory()->create([
            'email' => 'existing@example.com',
            'tenant_id' => $tenant1->id,
        ]);

        // Desde Tenant 2, intentar crear usuario con email que existe en Tenant 1
        $userData = [
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        // ESPERADO: Debería permitirse (201) porque es tenant diferente
        // ACTUAL: Falla porque AuthRequest::rules() consulta User::where('email')
        //         sin filtrado de tenant, concluye que es login, rechaza
        $response = $this->postJson('/api/v1/auth', $userData);
        $response->assertStatus(201);
    }

    /**
     * RIESGO 14 - ALTA: UserRequest no filtra tenant en validación unique()
     *
     * Ubicación: app/Http/Requests/UserRequest.php:25
     * Problema: Rule::unique('users', 'email')->ignore(Auth::id()) sin .where('tenant_id')
     * Comparar con ClientRequest.php:43-45 que sí usa .where('tenant_id', $tenantId)
     *
     * Sin .where('tenant_id'), la validación:
     * - Permite que User A (Tenant 1) se asigne email de User B (Tenant 2)
     * - O simplemente falla con constraint violation 500
     */
    #[Test]
    public function test_user_request_email_validation_ignores_tenant_isolation(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #14 ALTA - UserRequest ignora tenant en unique()');

        $tenant1 = Tenant::firstOrCreate(['slug' => 'tenant-1'], ['name' => 'Tenant One']);
        $tenant2 = Tenant::firstOrCreate(['slug' => 'tenant-2'], ['name' => 'Tenant Two']);

        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'tenant_id' => $tenant1->id,
        ]);

        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'tenant_id' => $tenant2->id,
        ]);

        // User 1 intenta actualizar su profile con el email de User 2
        $token = $user1->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/users/profile', [
                'name' => 'Updated User 1',
                'email' => 'user2@example.com', // Email de otro tenant!!
            ]);

        // ESPERADO: Debería rechazarse (422) - email está en uso en otro tenant
        // ACTUAL: Sin .where('tenant_id') en UserRequest, la validación no lo detecta
        //         o la validación pasa pero crea conflicto en BD
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * RIESGO 15 - CRÍTICA: User model NO usa BelongsToTenant trait
     *
     * Ubicación: app/Models/User.php (líneas 1-50)
     * Problema: User NO incluye use BelongsToTenant
     *
     * Comparativa:
     * - Client.php:17 → use BelongsToTenant ✓
     * - Cabin.php → use BelongsToTenant ✓
     * - Reservation.php → use BelongsToTenant ✓
     * - User.php → SIN BelongsToTenant ✗
     *
     * Sin el trait:
     * - No hay scope automático en queries
     * - No hay asignación automática de tenant_id en creating event
     * - Búsquedas directas User::where(...) ignoran tenant
     * - Inconsistencia arquitectónica
     */
    #[Test]
    public function test_user_model_missing_belongs_to_tenant_trait(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #15 CRÍTICA - User model falta BelongsToTenant');

        // Verificar que User NO usa BelongsToTenant trait
        $user = new User;
        $traits = class_uses($user);

        $this->assertArrayNotHasKey(
            \App\Traits\BelongsToTenant::class,
            $traits,
            'User model debe usar BelongsToTenant trait como Client, Cabin, Reservation'
        );

        // Crear usuarios en distintos tenants
        $tenant1 = Tenant::firstOrCreate(['slug' => 'tenant-1'], ['name' => 'Tenant One']);
        $tenant2 = Tenant::firstOrCreate(['slug' => 'tenant-2'], ['name' => 'Tenant Two']);

        /** @var User $user1 */
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        /** @var User $user2 */
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);

        // Simular contexto autenticado en Tenant 1
        $this->actingAs($user1);

        // ESPERADO: User::find($user2->id) debería retornar null (scope global)
        // ACTUAL: Retorna el usuario (sin BelongsToTenant trait, no hay scope)
        $foundUser = User::find($user2->id);
        $this->assertNull(
            $foundUser,
            'Con BelongsToTenant, User::find() debería aplicar scope automático de tenant'
        );

        // ALTERNATIVA: User::find($user2->id) debería fallar en scope
        // Actualmente: Retorna usuario del tenant diferente (data leakage)
    }

    /**
     * RIESGO 16 - CRÍTICA: AuthService.createUser() NO establece tenant_id
     *
     * Ubicación: app/Services/AuthService.php:30-37
     * Problema: createUser() crea usuario SIN asignar tenant_id
     *
     * Código actual:
     *   public function createUser(array $userData): User
     *   {
     *       return User::create([
     *           'name' => $userData['name'],
     *           'email' => $userData['email'],
     *           'password' => Hash::make($userData['password']),
     *           // tenant_id NO SE ASIGNA → Queda NULL !!
     *       ]);
     *   }
     *
     * Problemas:
     * 1. tenant_id queda NULL en BD
     * 2. AuthService no extiende Service (que sí lo asignaría automáticamente)
     * 3. Usuario registrado NO tiene aislamiento de tenant = Global
     * 4. Relacionado con riesgos 11 y 15: sin tenant_id, aislamiento es imposible
     */
    #[Test]
    public function test_auth_service_creates_user_without_tenant_id(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #16 CRÍTICA - AuthService.createUser() no asigna tenant_id');

        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/v1/auth', [
            ...$userData,
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);

        // ESPERADO: tenant_id debería establecerse automáticamente
        // ACTUAL: tenant_id es NULL
        $this->assertNotNull(
            $user->tenant_id,
            'Usuario registrado debe tener tenant_id asignado (no NULL)'
        );

        // VERIFICACIÓN: El usuario debería pertenecer a un tenant válido
        $this->assertGreaterThan(
            0,
            $user->tenant_id,
            'tenant_id debe ser un ID válido (> 0), no NULL'
        );
    }

    /**
     * RIESGO 11 + 12 + 15 + 16: Escenario integrado de login cruzado entre tenants
     *
     * Este test demuestra cómo los riesgos se combinan:
     * 1. User sin BelongsToTenant (15) → sin scope automático
     * 2. AuthService busca sin tenant context (12) → encuentra usuario global
     * 3. Email único simple (11) → bloquea el mismo email en tenants
     * 4. createUser sin tenant_id (16) → usuario sin aislamiento
     */
    #[Test]
    public function test_login_across_tenant_boundaries_due_to_missing_scopes(): void
    {
        $this->markTestIncomplete('TODO: Validar combinación crítica de Riesgos #11 + #12 + #15 + #16');

        $tenant1 = Tenant::firstOrCreate(['slug' => 'tenant-1'], ['name' => 'Tenant Alpha']);
        $tenant2 = Tenant::firstOrCreate(['slug' => 'tenant-2'], ['name' => 'Tenant Beta']);

        // Crear 2 usuarios con DISTINTO email (porque email único simple lo permite solo así)
        /** @var User $user1 */
        $user1 = User::factory()->create([
            'email' => 'alice@tenant1.local',
            'password' => bcrypt('Password123!'),
            'tenant_id' => $tenant1->id,
        ]);

        /** @var User $user2 */
        $user2 = User::factory()->create([
            'email' => 'alice@tenant2.local',
            'password' => bcrypt('Password123!'),
            'tenant_id' => $tenant2->id,
        ]);

        // ESCENARIO CRÍTICO:
        // 1. User 1 obtiene token de Tenant 1
        $token1 = $user1->createToken('auth')->plainTextToken;

        // 2. User 1 hace request a GET /api/v1/auth (autenticado en Tenant 1)
        $response1 = $this->withHeader('Authorization', 'Bearer '.$token1)
            ->getJson('/api/v1/auth');
        $response1->assertStatus(200);
        $this->assertEquals($user1->id, $response1->json('data.id'));

        // 3. AHORA: Simular que User 2 intenta leer el perfil de User 1
        //    (en un sistema vulnerable sin scope de tenant)
        $token2 = $user2->createToken('auth')->plainTextToken;

        // ESPERADO: User 2 NO debería poder acceder a User 1 (tenant diferente)
        // ACTUAL: User::find($user1->id) retorna el usuario (sin BelongsToTenant)
        $this->actingAs($user2);
        $foundUser = User::find($user1->id);

        $this->assertNull(
            $foundUser,
            'Sin BelongsToTenant, User::find() no aplica scope de tenant → DATA LEAKAGE'
        );
    }
}
