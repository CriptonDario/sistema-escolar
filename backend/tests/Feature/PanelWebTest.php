<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Grado;
use App\Models\Personal;
use App\Models\Seccion;
use App\Models\User;
use App\Support\Ajustes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PanelWebTest extends TestCase
{
    use RefreshDatabase;

    private function entrarComo(Rol $rol): User
    {
        $user = User::factory()->rol($rol)->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_sin_sesion_todo_lleva_al_login(): void
    {
        $this->get('/')->assertRedirect('/panel');
        $this->get('/panel')->assertRedirect(route('login'));
        $this->get('/alumnos')->assertRedirect(route('login'));
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión')->assertSee('Contraseña');
    }

    public function test_el_admin_inicia_sesion_y_llega_al_panel(): void
    {
        User::factory()->create(['usuario' => 'admin', 'password' => 'Clave1234', 'nombre' => 'Dirección General']);

        $this->post('/login', ['usuario' => 'Admin', 'clave' => 'Clave1234'])->assertRedirect(route('panel'));
        $this->assertAuthenticated();

        $this->get('/panel')->assertOk()->assertSee('Dirección General')->assertSee('Grados y secciones');
    }

    public function test_rechaza_clave_incorrecta_y_bloquea_tras_cinco_intentos(): void
    {
        User::factory()->create(['usuario' => 'admin', 'password' => 'Clave1234']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['usuario' => 'admin', 'clave' => 'mala'])->assertSessionHasErrors('usuario');
        }
        $this->assertGuest();

        $this->post('/login', ['usuario' => 'admin', 'clave' => 'Clave1234'])->assertSessionHasErrors('usuario');
        $this->assertGuest();
    }

    public function test_las_cuentas_de_la_app_no_entran_al_panel(): void
    {
        // Alumnos y docentes usan solo la app. (Los apoderados tienen su propio
        // portal web: ver PortalFamiliaTest.)
        User::factory()->rol(Rol::Alumno)->create(['usuario' => 'alumno@colegio.edu.pe', 'password' => 'Clave1234']);
        User::factory()->rol(Rol::Personal)->create(['usuario' => 'docente@colegio.edu.pe', 'password' => 'Clave1234']);

        $this->post('/login', ['usuario' => 'alumno@colegio.edu.pe', 'clave' => 'Clave1234'])->assertSessionHasErrors('usuario');
        $this->post('/login', ['usuario' => 'docente@colegio.edu.pe', 'clave' => 'Clave1234'])->assertSessionHasErrors('usuario');
        $this->assertGuest();
    }

    public function test_las_pantallas_usan_el_nombre_logo_y_color_del_colegio(): void
    {
        app(Ajustes::class)->guardar('nombre_colegio', 'I.E.P. Rabí Olam');
        app(Ajustes::class)->guardar('color_primario', '#FD0D0D');
        app(Ajustes::class)->guardar('logo_url', 'images/logo-rabi-olam.png');

        $this->get('/login')->assertOk()
            ->assertSee('I.E.P. Rabí Olam')
            ->assertSee('--col-primario: #fd0d0d', false)
            ->assertSee('--col-primario-rgb: 253,13,13', false)
            ->assertSee('images/logo-rabi-olam.png');
    }

    public function test_cerrar_sesion(): void
    {
        $this->entrarComo(Rol::Admin);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_el_guardia_ve_el_kiosco_pero_no_la_administracion(): void
    {
        $this->entrarComo(Rol::Guardia);

        $this->get('/panel')->assertOk()->assertSee('Abrir Kiosco')->assertDontSee('Grados y secciones');
        $this->get('/kiosco')->assertOk()->assertSee('Código del carné');
        $this->get('/alumnos')->assertForbidden();
        $this->get('/grados')->assertForbidden();
    }

    public function test_el_kiosco_marca_entrada_de_alumno_y_de_personal(): void
    {
        Queue::fake();
        $this->entrarComo(Rol::Guardia);
        $seccion = Seccion::factory()->for(Grado::factory()->create(['nombre' => '1ro Primaria']))->create(['nombre' => 'B']);
        Alumno::factory()->for($seccion)->create(['codigo' => '1PB01', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
        Personal::factory()->create(['codigo' => 'DOC01', 'cargo' => 'Docente']);
        $this->travelTo('2026-03-10 07:15:00');

        $this->postJson('/kiosco/marcar', ['codigo' => '1PB01'])
            ->assertOk()
            ->assertJsonPath('evento', 'entrada')
            ->assertJsonPath('nombre', 'Ana Pérez')
            ->assertJsonPath('detalle', '1ro Primaria B');

        $this->postJson('/kiosco/marcar', ['codigo' => 'DOC01'])->assertOk()->assertJsonPath('detalle', 'Docente');
        $this->postJson('/kiosco/marcar', ['codigo' => 'NADA'])->assertUnprocessable();

        $this->assertDatabaseCount('asistencias', 2);
        $this->get('/panel')->assertOk()->assertSee('Ana Pérez');
    }

    public function test_grados_y_secciones_desde_el_panel(): void
    {
        $this->entrarComo(Rol::Admin);

        $this->post('/grados', ['nivel' => 'inicial', 'nombre' => '3 años', 'orden' => 1, 'secciones' => ['a', 'b']])
            ->assertRedirect()->assertSessionHas('ok');

        $grado = Grado::query()->where('nombre', '3 años')->firstOrFail();
        $this->assertSame(['A', 'B'], $grado->secciones()->pluck('nombre')->all());

        $this->post("/grados/{$grado->id}/secciones")->assertSessionHas('ok');
        $this->assertSame(['A', 'B', 'C'], $grado->secciones()->pluck('nombre')->all());

        $seccion = $grado->secciones()->where('nombre', 'C')->firstOrFail();
        $this->post("/secciones/{$seccion->id}/alternar");
        $this->assertFalse($seccion->fresh()->activo);

        $this->delete("/secciones/{$seccion->id}")->assertSessionHas('ok');
        $this->assertDatabaseMissing('secciones', ['id' => $seccion->id]);

        $this->get('/grados')->assertOk()->assertSee('3 años')->assertSee('Inicial');
    }

    public function test_no_elimina_una_seccion_con_alumnos(): void
    {
        $this->entrarComo(Rol::Admin);
        $seccion = Seccion::factory()->create();
        Alumno::factory()->for($seccion)->create();

        $this->delete("/secciones/{$seccion->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('secciones', ['id' => $seccion->id]);
    }

    public function test_alta_edicion_y_baja_de_alumnos(): void
    {
        $this->entrarComo(Rol::Admin);
        $seccion = Seccion::factory()->for(Grado::factory()->create(['nombre' => '2do Secundaria']))->create(['nombre' => 'B']);

        $this->get('/alumnos/create')->assertOk()->assertSee('2do Secundaria B');

        $this->post('/alumnos', [
            'codigo' => '2SB01', 'nombres' => 'Ana', 'apellidos' => "D'Angelo", 'seccion_id' => $seccion->id,
            'correo' => 'ana@colegio.edu.pe',
        ])->assertRedirect(route('alumnos.index'));

        $alumno = Alumno::query()->where('codigo', '2SB01')->firstOrFail();
        $this->assertSame('ana@colegio.edu.pe', $alumno->user->usuario);

        $this->get('/alumnos')->assertOk()->assertSee("D'Angelo, Ana")->assertSee('2do Secundaria B');
        $this->get('/alumnos?q=nadie')->assertOk()->assertSee('No hay alumnos que mostrar');

        // Editar sin marcar "activo" lo da de baja y le cierra la cuenta.
        $this->put("/alumnos/{$alumno->id}", ['codigo' => '2SB01', 'nombres' => 'Ana María', 'apellidos' => "D'Angelo"])
            ->assertRedirect(route('alumnos.index'));
        $alumno->refresh();
        $this->assertSame('Ana María', $alumno->nombres);
        $this->assertFalse($alumno->activo);
        $this->assertFalse($alumno->user->fresh()->activo);

        $this->post('/alumnos', ['codigo' => '2SB01', 'nombres' => 'Otro', 'apellidos' => 'Más'])->assertSessionHasErrors('codigo');
    }

    public function test_alta_de_personal(): void
    {
        $this->entrarComo(Rol::Admin);

        $this->get('/personal/create')->assertOk();
        $this->post('/personal', ['codigo' => 'DOC01', 'nombres' => 'Luis', 'apellidos' => 'Ramos', 'tipo' => 'docente', 'cargo' => 'Docente'])
            ->assertRedirect(route('personal.index'));

        $persona = Personal::query()->where('codigo', 'DOC01')->firstOrFail();
        $this->get('/personal')->assertOk()->assertSee('Ramos, Luis');
        $this->get("/personal/{$persona->id}/edit")->assertOk()->assertSee('DOC01');

        $this->delete("/personal/{$persona->id}")->assertSessionHas('ok');
        $this->assertFalse($persona->fresh()->activo);
    }

    public function test_apoderados_desde_el_panel(): void
    {
        $this->entrarComo(Rol::Admin);
        $alumnos = Alumno::factory()->count(2)->create();

        $this->get('/apoderados')->assertOk()->assertSee('2')->assertSee('sin ningún apoderado');

        $this->post('/apoderados', [
            'nombres' => 'María Pérez', 'telefono' => '987 654 321',
            'hijos' => [['alumno_id' => $alumnos[0]->id, 'parentesco' => 'madre']],
        ])->assertSessionHas('ok');

        $apoderado = Apoderado::query()->where('telefono', '987654321')->firstOrFail();
        $this->assertTrue($apoderado->esResponsableDe($alumnos[0]->id));
        $this->assertSame(Rol::Apoderado, $apoderado->user->rol);

        $this->post("/apoderados/{$apoderado->id}/hijos", ['alumno_id' => $alumnos[1]->id])->assertSessionHas('ok');
        $this->assertSame(2, $apoderado->hijos()->count());

        $this->delete("/apoderados/{$apoderado->id}/hijos/{$alumnos[1]->id}")->assertSessionHas('ok');
        $this->assertSame(1, $apoderado->hijos()->count());

        $this->post("/apoderados/{$apoderado->id}/alternar");
        $this->assertFalse($apoderado->user->fresh()->activo);

        $this->post("/apoderados/{$apoderado->id}/restablecer-clave")->assertSessionHas('ok');
        $this->get('/apoderados')->assertOk()->assertSee('María Pérez')->assertSee('987654321');
    }

    public function test_una_cuenta_desactivada_sale_del_panel_en_la_siguiente_peticion(): void
    {
        $user = $this->entrarComo(Rol::Admin);
        $this->get('/panel')->assertOk();

        $user->update(['activo' => false]);

        $this->get('/panel')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
