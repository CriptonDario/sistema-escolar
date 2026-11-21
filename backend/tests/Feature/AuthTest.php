<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_devuelve_token_y_perfil_con_su_rol(): void
    {
        User::factory()->rol(Rol::Guardia)->create(['usuario' => 'porteria', 'password' => 'Clave1234']);

        $this->postJson('/api/v1/auth/login', ['usuario' => 'Porteria', 'clave' => 'Clave1234'])
            ->assertOk()
            ->assertJsonPath('perfil.rol', 'guardia')
            ->assertJsonPath('perfil.debe_cambiar_clave', false)
            ->assertJsonStructure(['token', 'perfil' => ['id', 'nombre', 'usuario'], 'colegio' => ['nombre']]);
    }

    public function test_el_perfil_de_un_alumno_trae_el_codigo_de_su_carne(): void
    {
        $cuenta = User::factory()->rol(Rol::Alumno)->create(['usuario' => 'ana@colegio.edu.pe', 'password' => 'Clave1234']);
        Alumno::factory()->create(['codigo' => '2SB01', 'user_id' => $cuenta->id]);

        $this->postJson('/api/v1/auth/login', ['usuario' => 'ana@colegio.edu.pe', 'clave' => 'Clave1234'])
            ->assertOk()
            ->assertJsonPath('perfil.codigo', '2SB01');
    }

    public function test_login_rechaza_clave_incorrecta_y_cuenta_inactiva(): void
    {
        User::factory()->create(['usuario' => 'admin', 'password' => 'Clave1234']);
        User::factory()->inactivo()->create(['usuario' => 'baja', 'password' => 'Clave1234']);

        $this->postJson('/api/v1/auth/login', ['usuario' => 'admin', 'clave' => 'mala'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['usuario' => 'baja', 'clave' => 'Clave1234'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['usuario' => 'nadie', 'clave' => 'Clave1234'])->assertUnauthorized();
    }

    public function test_login_se_bloquea_tras_cinco_intentos_fallidos(): void
    {
        User::factory()->create(['usuario' => 'admin', 'password' => 'Clave1234']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['usuario' => 'admin', 'clave' => 'mala'])->assertUnauthorized();
        }

        // Bloqueada incluso con la contraseña correcta.
        $this->postJson('/api/v1/auth/login', ['usuario' => 'admin', 'clave' => 'Clave1234'])->assertStatus(429);
    }

    public function test_apoderado_entra_con_su_telefono_escrito_de_cualquier_forma(): void
    {
        Apoderado::factory()->create([
            'telefono' => '987654321',
            'user_id' => User::factory()->rol(Rol::Apoderado)->create(['usuario' => '987654321', 'password' => 'Clave1234']),
        ]);

        $this->postJson('/api/v1/auth/login', ['usuario' => '+51 987 654 321', 'clave' => 'Clave1234'])
            ->assertOk()
            ->assertJsonPath('perfil.rol', 'apoderado');
    }

    public function test_con_la_clave_inicial_solo_se_puede_cambiarla(): void
    {
        User::factory()->rol(Rol::Apoderado)->conClaveInicial()->create(['usuario' => '900000001']);

        $token = $this->postJson('/api/v1/auth/login', ['usuario' => '900000001', 'clave' => '123456'])
            ->assertOk()
            ->assertJsonPath('perfil.debe_cambiar_clave', true)
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/hijos')
            ->assertForbidden()
            ->assertJsonPath('codigo', 'cambiar_clave');

        $this->withToken($token)->getJson('/api/v1/perfil')->assertOk();

        // No vale "cambiarla" por la misma contraseña inicial ni por una débil.
        $this->withToken($token)->postJson('/api/v1/auth/clave', ['clave_actual' => '123456', 'clave_nueva' => '123456'])
            ->assertUnprocessable();
        $this->withToken($token)->postJson('/api/v1/auth/clave', ['clave_actual' => '123456', 'clave_nueva' => 'abcdefgh'])
            ->assertUnprocessable();

        $this->withToken($token)->postJson('/api/v1/auth/clave', ['clave_actual' => '123456', 'clave_nueva' => 'MiClave2026'])
            ->assertOk();

        $this->withToken($token)->getJson('/api/v1/perfil')->assertJsonPath('data.debe_cambiar_clave', false);
        $this->postJson('/api/v1/auth/login', ['usuario' => '900000001', 'clave' => 'MiClave2026'])->assertOk();
    }

    public function test_cambiar_clave_exige_la_actual(): void
    {
        User::factory()->create(['usuario' => 'admin', 'password' => 'Clave1234']);
        $token = $this->postJson('/api/v1/auth/login', ['usuario' => 'admin', 'clave' => 'Clave1234'])->json('token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/clave', ['clave_actual' => 'otra', 'clave_nueva' => 'MiClave2026'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('clave_actual');
    }

    public function test_logout_invalida_el_token(): void
    {
        User::factory()->create(['usuario' => 'admin', 'password' => 'Clave1234']);
        $token = $this->postJson('/api/v1/auth/login', ['usuario' => 'admin', 'clave' => 'Clave1234'])->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/perfil')->assertUnauthorized();
    }

    public function test_una_cuenta_desactivada_deja_de_entrar_en_el_acto(): void
    {
        $user = User::factory()->rol(Rol::Guardia)->create(['usuario' => 'porteria', 'password' => 'Clave1234']);
        $token = $this->postJson('/api/v1/auth/login', ['usuario' => 'porteria', 'clave' => 'Clave1234'])->json('token');

        $this->withToken($token)->getJson('/api/v1/asistencia/hoy')->assertOk();

        $user->update(['activo' => false]);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/asistencia/hoy')->assertUnauthorized();
    }

    public function test_sin_token_la_api_responde_401_en_json(): void
    {
        $this->get('/api/v1/perfil')->assertUnauthorized()->assertJsonStructure(['message']);
    }
}
