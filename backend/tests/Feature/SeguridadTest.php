<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Apoderado;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    // ── Cabeceras ────────────────────────────────────────────────────────────

    public function test_las_paginas_llevan_las_cabeceras_de_seguridad(): void
    {
        $respuesta = $this->get('/login')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $respuesta->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        // Sin https no se promete https.
        $this->assertFalse($respuesta->headers->has('Strict-Transport-Security'));

        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_la_api_tambien_las_lleva_y_tiene_un_tope_de_peticiones(): void
    {
        $respuesta = $this->postJson('/api/v1/auth/login', ['usuario' => 'nadie', 'clave' => 'x'])->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-RateLimit-Limit', 60);

        $this->assertFalse($respuesta->headers->has('Content-Security-Policy'));
    }

    // ── Freno por dirección ──────────────────────────────────────────────────

    public function test_probar_una_contrasena_contra_muchas_cuentas_bloquea_esa_direccion(): void
    {
        User::factory()->create(['usuario' => 'admin', 'password' => 'Clave1234']);

        // La contraseña inicial contra 40 teléfonos distintos: ninguna cuenta llega a 5 fallos.
        foreach (range(1, 40) as $i) {
            $this->post('/login', ['usuario' => '9000000'.sprintf('%02d', $i), 'clave' => '123456'])->assertSessionHasErrors('usuario');
        }

        // Desde esa dirección ya no se entra, ni con la contraseña correcta, ni por la app.
        $this->post('/login', ['usuario' => 'admin', 'clave' => 'Clave1234'])->assertSessionHasErrors('usuario');
        $this->assertGuest();
        $this->postJson('/api/v1/auth/login', ['usuario' => 'admin', 'clave' => 'Clave1234'])->assertStatus(429);

        // Desde otra dirección, sí.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post('/login', ['usuario' => 'admin', 'clave' => 'Clave1234'])->assertRedirect(route('panel'));

        $this->assertTrue(Auditoria::query()->where('evento', 'login_bloqueado')->exists());
    }

    // ── Registro de seguridad ────────────────────────────────────────────────

    public function test_se_anotan_los_ingresos_y_los_intentos_fallidos_sin_guardar_contrasenas(): void
    {
        $admin = User::factory()->create(['usuario' => 'admin', 'password' => 'Clave1234']);

        $this->post('/login', ['usuario' => 'admin', 'clave' => 'MalaClave99']);
        $this->post('/login', ['usuario' => 'admin', 'clave' => 'Clave1234'])->assertRedirect(route('panel'));

        $this->assertDatabaseHas('auditoria', ['evento' => 'login_fallido', 'detalle' => 'admin', 'user_id' => null]);
        $this->assertDatabaseHas('auditoria', ['evento' => 'login_web', 'user_id' => $admin->id, 'ip' => '127.0.0.1']);
        $this->assertSame(0, Auditoria::query()->where('detalle', 'like', '%MalaClave99%')->orWhere('detalle', 'like', '%Clave1234%')->count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['usuario' => 'admin', 'clave' => 'Clave1234'])->assertOk();
        $this->assertDatabaseHas('auditoria', ['evento' => 'login_app', 'user_id' => $admin->id]);
    }

    public function test_el_administrador_ve_el_registro_y_el_aviso_de_cuentas_sin_activar(): void
    {
        Apoderado::factory()->create(['user_id' => User::factory()->rol(Rol::Apoderado)->conClaveInicial()->create()]);
        $this->post('/login', ['usuario' => 'intruso', 'clave' => 'x']);

        $this->actingAs(User::factory()->create());
        $this->get('/seguridad')->assertOk()
            ->assertSee('Intento fallido')->assertSee('intruso')
            ->assertSee('1 cuenta(s) todavía usan la contraseña inicial');
        $this->get('/seguridad?evento=login_bloqueado')->assertOk()->assertDontSee('intruso');
        $this->get('/panel')->assertOk()->assertSee(route('seguridad.index'), false);

        $this->actingAs(User::factory()->rol(Rol::Guardia)->create())->get('/seguridad')->assertForbidden();
        $this->actingAs(User::factory()->rol(Rol::Direccion)->create())->get('/seguridad')->assertForbidden();
    }

    public function test_se_anotan_los_cambios_sensibles(): void
    {
        $admin = User::factory()->create(['password' => 'Clave1234']);
        $apoderado = Apoderado::factory()->create();
        $this->actingAs($admin);

        $this->post('/usuarios', ['nombre' => 'Portería', 'usuario' => 'puerta2', 'rol' => 'guardia', 'clave' => 'Guardia2026'])->assertSessionHasNoErrors();
        $this->post("/apoderados/{$apoderado->id}/restablecer-clave")->assertSessionHasNoErrors();
        $this->post('/mi-cuenta/clave', ['clave_actual' => 'Clave1234', 'clave_nueva' => 'OtraClave2026', 'clave_nueva_confirmation' => 'OtraClave2026'])->assertSessionHasNoErrors();
        $this->put('/integraciones', ['wa_proveedor' => 'ultramsg', 'sunat_serie_boleta' => 'B001', 'sunat_serie_factura' => 'F001', 'sunat_afectacion' => 'exonerado', 'wa_activo' => '1'])->assertSessionHasNoErrors();

        foreach (['usuario_creado', 'clave_restablecida', 'clave_cambiada', 'integraciones'] as $evento) {
            $this->assertDatabaseHas('auditoria', ['evento' => $evento, 'user_id' => $admin->id]);
        }
        $this->assertSame(0, Auditoria::query()->where('detalle', 'like', '%Guardia2026%')->orWhere('detalle', 'like', '%OtraClave2026%')->count());
    }

    // ── Cierre de sesiones ───────────────────────────────────────────────────

    public function test_al_cambiarle_la_contrasena_o_desactivar_una_cuenta_se_cierran_sus_sesiones(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::factory()->create();
        $guardia = User::factory()->rol(Rol::Guardia)->create();

        $abrirSesiones = function () use ($guardia) {
            $guardia->createToken('app');
            DB::table('sessions')->insert(['id' => 'sesion-'.uniqid(), 'user_id' => $guardia->id, 'payload' => '', 'last_activity' => time()]);
        };

        $abrirSesiones();
        $this->actingAs($admin)->put("/usuarios/{$guardia->id}", ['nombre' => 'Guardia', 'rol' => 'guardia', 'clave' => 'NuevaClave2026'])->assertSessionHasNoErrors();
        $this->assertSame(0, $guardia->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $guardia->id)->count());

        // Cambiarle solo el nombre no lo saca.
        $abrirSesiones();
        $this->put("/usuarios/{$guardia->id}", ['nombre' => 'Guardia de turno', 'rol' => 'guardia'])->assertSessionHasNoErrors();
        $this->assertSame(1, $guardia->tokens()->count());

        $this->post("/usuarios/{$guardia->id}/alternar")->assertSessionHasNoErrors();
        $this->assertSame(0, $guardia->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $guardia->id)->count());
        $this->assertDatabaseHas('auditoria', ['evento' => 'usuario_desactivado', 'detalle' => $guardia->usuario]);
    }

    // ── Revisión antes de publicar ───────────────────────────────────────────

    public function test_la_revision_marca_una_configuracion_insegura(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://colegio.test', 'session.secure' => null]);

        $this->artisan('colegio:revisar-seguridad')
            ->expectsOutputToContain('APP_DEBUG=false')
            ->expectsOutputToContain('CORREGIR')
            ->assertFailed();
    }

    public function test_la_revision_aprueba_una_configuracion_de_produccion(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'app.debug' => false, 'app.url' => 'https://colegio.edu.pe', 'session.secure' => true, 'session.encrypt' => true,
            'colegio.clave_inicial' => 'Familia-Rabi-2026', 'colegio.push.credenciales_fcm' => null,
        ]);
        User::factory()->create();

        $this->artisan('colegio:revisar-seguridad')->expectsOutputToContain('segura para producción')->assertSuccessful();
    }
}
