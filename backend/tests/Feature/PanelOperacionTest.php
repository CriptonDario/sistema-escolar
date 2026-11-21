<?php

namespace Tests\Feature;

use App\Enums\Nivel;
use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Personal;
use App\Models\User;
use App\Support\Ajustes;
use App\Support\Escalas;
use App\Support\Periodos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PanelOperacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->admin = User::factory()->create(['usuario' => 'admin', 'password' => 'Clave1234']);
        $this->actingAs($this->admin);
    }

    public function test_registra_y_anula_un_permiso_desde_el_panel(): void
    {
        $alumno = Alumno::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez']);
        Apoderado::factory()->create()->hijos()->attach($alumno);

        $this->get('/permisos')->assertOk()->assertSee('Nuevo permiso');

        $this->post('/permisos', [
            'alumno_id' => $alumno->id, 'tipo' => 'salida_anticipada', 'fecha' => today()->toDateString(),
            'hora' => '11:30', 'motivo' => 'Cita médica',
        ])->assertSessionHas('ok');

        $this->assertDatabaseHas('mensajes', ['tipo' => 'permiso', 'titulo' => 'Permiso: Salida anticipada']);
        $this->get('/permisos')->assertSee('Ana Pérez')->assertSee('Cita médica');

        $permiso = $alumno->permisos()->firstOrFail();
        $this->delete("/permisos/{$permiso->id}")->assertSessionHas('ok');
        $this->assertNotNull($permiso->fresh()->anulado_en);
        $this->assertDatabaseHas('mensajes', ['titulo' => 'Permiso anulado']);
    }

    public function test_el_permiso_valida_la_hora(): void
    {
        $alumno = Alumno::factory()->create();

        $this->post('/permisos', ['alumno_id' => $alumno->id, 'tipo' => 'ingreso_tarde', 'fecha' => today()->toDateString(), 'motivo' => 'X'])
            ->assertSessionHasErrors('hora');
    }

    public function test_historial_filtra_por_tipo_fecha_y_nombre(): void
    {
        $ana = Alumno::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez', 'codigo' => 'A1']);
        $luis = Alumno::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Gómez', 'codigo' => 'A2']);
        $docente = Personal::factory()->create(['nombres' => 'Marta', 'apellidos' => 'Ríos']);

        $ana->asistencias()->create(['fecha' => '2026-03-10', 'hora_entrada' => '07:20:00', 'estado' => 'puntual']);
        $luis->asistencias()->create(['fecha' => '2026-03-10', 'hora_entrada' => '08:00:00', 'estado' => 'tarde']);
        $luis->asistencias()->create(['fecha' => '2026-03-11', 'hora_entrada' => '07:10:00', 'estado' => 'puntual']);
        $docente->asistencias()->create(['fecha' => '2026-03-10', 'hora_entrada' => '07:00:00', 'estado' => 'puntual']);

        $this->get('/historial?desde=2026-03-10&hasta=2026-03-10')
            ->assertOk()->assertSee('Pérez, Ana')->assertSee('Gómez, Luis')->assertDontSee('Ríos, Marta');

        $this->get('/historial?desde=2026-03-10&hasta=2026-03-11&q=ana')
            ->assertSee('Pérez, Ana')->assertDontSee('Gómez, Luis');

        $this->get('/historial?tipo=personal&desde=2026-03-10&hasta=2026-03-10')
            ->assertSee('Ríos, Marta')->assertDontSee('Pérez, Ana');
    }

    public function test_descarga_el_historial_para_excel(): void
    {
        $ana = Alumno::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez', 'codigo' => 'A1']);
        $ana->asistencias()->create(['fecha' => '2026-03-10', 'hora_entrada' => '07:20:00', 'hora_salida' => '13:05:00', 'estado' => 'puntual']);

        $respuesta = $this->get('/historial/exportar?desde=2026-03-10&hasta=2026-03-10')->assertOk();

        $this->assertStringContainsString('asistencia_alumno_2026-03-10_a_2026-03-10.csv', $respuesta->headers->get('content-disposition'));
        $csv = $respuesta->streamedContent();
        $this->assertStringContainsString('Fecha;Código;Apellidos', $csv);
        $this->assertStringContainsString('10/03/2026;A1;Pérez;Ana;', $csv);
        $this->assertStringContainsString(';07:20;13:05;Puntual', $csv);
    }

    public function test_direccion_consulta_el_historial_pero_no_administra(): void
    {
        $this->actingAs(User::factory()->rol(Rol::Direccion)->create());

        $this->get('/historial')->assertOk();
        $this->get('/permisos')->assertForbidden();
        $this->get('/usuarios')->assertForbidden();
        $this->get('/kiosco')->assertForbidden();
    }

    public function test_crea_edita_y_desactiva_usuarios_del_sistema(): void
    {
        $this->get('/usuarios')->assertOk()->assertSee('admin');

        $this->post('/usuarios', ['nombre' => 'Portería', 'usuario' => 'Porteria', 'rol' => 'guardia', 'clave' => 'Guardia2026'])
            ->assertSessionHas('ok');

        $guardia = User::query()->where('usuario', 'porteria')->firstOrFail();
        $this->assertSame(Rol::Guardia, $guardia->rol);
        $this->assertTrue(Hash::check('Guardia2026', $guardia->password));

        $this->put("/usuarios/{$guardia->id}", ['nombre' => 'Puerta principal', 'rol' => 'direccion', 'clave' => ''])->assertSessionHas('ok');
        $guardia->refresh();
        $this->assertSame('Puerta principal', $guardia->nombre);
        $this->assertSame(Rol::Direccion, $guardia->rol);
        $this->assertTrue(Hash::check('Guardia2026', $guardia->password)); // sin clave nueva, se conserva

        $this->post("/usuarios/{$guardia->id}/alternar")->assertSessionHas('ok');
        $this->assertFalse($guardia->fresh()->activo);
    }

    public function test_reglas_de_las_cuentas_del_sistema(): void
    {
        $this->post('/usuarios', ['nombre' => 'X', 'usuario' => 'admin', 'rol' => 'guardia', 'clave' => 'Guardia2026'])->assertSessionHasErrors('usuario');
        $this->post('/usuarios', ['nombre' => 'X', 'usuario' => 'nuevo', 'rol' => 'apoderado', 'clave' => 'Guardia2026'])->assertSessionHasErrors('rol');
        $this->post('/usuarios', ['nombre' => 'X', 'usuario' => 'nuevo', 'rol' => 'guardia', 'clave' => 'debil'])->assertSessionHasErrors('clave');

        // El administrador no puede dejarse a sí mismo fuera.
        $this->post("/usuarios/{$this->admin->id}/alternar")->assertSessionHas('error');
        $this->put("/usuarios/{$this->admin->id}", ['nombre' => 'Yo', 'rol' => 'guardia'])->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->activo);
        $this->assertSame(Rol::Admin, $this->admin->fresh()->rol);

        // Las cuentas de la app no se gestionan desde esta pantalla.
        $apoderado = User::factory()->rol(Rol::Apoderado)->create();
        $this->post("/usuarios/{$apoderado->id}/alternar")->assertNotFound();
    }

    public function test_cambia_su_propia_contrasena(): void
    {
        $this->post('/mi-cuenta/clave', ['clave_actual' => 'mala', 'clave_nueva' => 'Nueva2026x', 'clave_nueva_confirmation' => 'Nueva2026x'])
            ->assertSessionHasErrors('clave_actual');
        $this->post('/mi-cuenta/clave', ['clave_actual' => 'Clave1234', 'clave_nueva' => 'Nueva2026x', 'clave_nueva_confirmation' => 'Otra2026x'])
            ->assertSessionHasErrors('clave_nueva');

        $this->post('/mi-cuenta/clave', ['clave_actual' => 'Clave1234', 'clave_nueva' => 'Nueva2026x', 'clave_nueva_confirmation' => 'Nueva2026x'])
            ->assertSessionHas('ok');

        $this->assertTrue(Hash::check('Nueva2026x', $this->admin->fresh()->password));
    }

    public function test_guarda_la_configuracion_y_cambia_la_regla_de_tardanza(): void
    {
        $this->get('/configuracion')->assertOk()->assertSee('Hora de entrada');

        $this->put('/configuracion', [
            'nombre_colegio' => 'I.E.P. Rabí Olam', 'color_primario' => '#fd0d0d',
            'hora_entrada' => '08:00', 'tolerancia_minutos' => 5, 'minutos_minimos_para_salida' => 30,
            'periodos_por_anio' => 2,
            'escalas' => ['inicial' => 'literal', 'primaria' => 'numerica', 'secundaria' => 'numerica'],
        ])->assertSessionHas('ok');

        $this->assertSame('numerica', app(Escalas::class)->del(Nivel::Primaria)->value);

        $this->assertSame('Semestre', app(Periodos::class)->nombre());

        $ajustes = app(Ajustes::class);
        $this->assertSame('08:00', $ajustes->horaEntrada());
        $this->assertSame(5, $ajustes->toleranciaMinutos());
        $this->assertSame(30, $ajustes->minutosMinimosParaSalida());
        // Cada petición real arranca limpia; en la prueba hay que pedirlo.
        $this->app->forgetScopedInstances();
        $this->get('/panel')->assertSee('I.E.P. Rabí Olam')->assertSee('--col-primario: #fd0d0d', false);

        // 08:04 ya no es tardanza con la nueva hora y tolerancia.
        Alumno::factory()->create(['codigo' => 'A1']);
        $this->travelTo('2026-03-10 08:04:00');
        $this->postJson('/kiosco/marcar', ['codigo' => 'A1'])->assertJsonPath('evento', 'entrada');
    }

    public function test_sube_el_logo_del_colegio(): void
    {
        $this->put('/configuracion', [
            'nombre_colegio' => 'Colegio', 'color_primario' => '#0d6efd', 'hora_entrada' => '07:30',
            'tolerancia_minutos' => 0, 'minutos_minimos_para_salida' => 60, 'periodos_por_anio' => 3,
            'escalas' => ['inicial' => 'literal', 'primaria' => 'literal', 'secundaria' => 'numerica'],
            'logo' => UploadedFile::fake()->image('escudo.png', 200, 200),
        ])->assertSessionHas('ok');

        $ruta = app(Ajustes::class)->obtener('logo_url');
        $this->assertStringStartsWith('images/marca/logo-', $ruta);
        $this->assertFileExists(public_path($ruta));
        File::delete(public_path($ruta));
    }

    public function test_la_configuracion_rechaza_valores_y_archivos_invalidos(): void
    {
        $this->put('/configuracion', [
            'nombre_colegio' => '', 'color_primario' => 'rojo', 'hora_entrada' => '25:00',
            'tolerancia_minutos' => -1, 'minutos_minimos_para_salida' => 60,
            'logo' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors(['nombre_colegio', 'color_primario', 'hora_entrada', 'tolerancia_minutos', 'logo']);
    }
}
