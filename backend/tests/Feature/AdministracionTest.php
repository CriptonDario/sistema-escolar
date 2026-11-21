<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Personal;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdministracionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->comoRol(Rol::Admin);
    }

    public function test_crea_un_alumno_con_cuenta_que_nace_con_la_clave_inicial(): void
    {
        $seccion = Seccion::factory()->create(['nombre' => 'B']);

        $this->postJson('/api/v1/admin/alumnos', [
            'codigo' => '1PB01', 'nombres' => 'Ana', 'apellidos' => "D'Angelo", 'seccion_id' => $seccion->id,
            'correo' => 'Ana@Colegio.edu.pe',
        ])
            ->assertCreated()
            ->assertJsonPath('data.apellidos', "D'Angelo")
            ->assertJsonPath('data.usuario', 'ana@colegio.edu.pe')
            ->assertJsonPath('data.aula', $seccion->nombreCompleto());

        $cuenta = User::query()->where('usuario', 'ana@colegio.edu.pe')->firstOrFail();
        $this->assertSame(Rol::Alumno, $cuenta->rol);
        $this->assertTrue($cuenta->debe_cambiar_clave);
        $this->assertTrue(Hash::check('123456', $cuenta->password));
    }

    public function test_un_alumno_sin_correo_no_tiene_cuenta(): void
    {
        $this->postJson('/api/v1/admin/alumnos', ['codigo' => 'X1', 'nombres' => 'Luis', 'apellidos' => 'Rojas'])
            ->assertCreated()
            ->assertJsonPath('data.usuario', null);

        $this->assertDatabaseCount('users', 1); // solo el admin de la prueba
    }

    public function test_valida_codigo_duplicado_y_correo_en_uso(): void
    {
        Alumno::factory()->create(['codigo' => 'REP01']);
        User::factory()->create(['usuario' => 'ocupado@colegio.edu.pe']);

        $this->postJson('/api/v1/admin/alumnos', [
            'codigo' => 'REP01', 'nombres' => 'A', 'apellidos' => 'B', 'correo' => 'ocupado@colegio.edu.pe',
        ])->assertUnprocessable()->assertJsonValidationErrors(['codigo', 'correo']);
    }

    public function test_dar_de_baja_a_un_alumno_conserva_su_historial_y_le_cierra_el_acceso(): void
    {
        $cuenta = User::factory()->rol(Rol::Alumno)->create();
        $alumno = Alumno::factory()->create(['user_id' => $cuenta->id]);
        $alumno->asistencias()->create(['fecha' => '2026-03-10', 'hora_entrada' => '07:20:00', 'estado' => 'puntual']);

        $this->deleteJson("/api/v1/admin/alumnos/{$alumno->id}")->assertOk();

        $this->assertFalse($alumno->fresh()->activo);
        $this->assertFalse($cuenta->fresh()->activo);
        $this->assertDatabaseCount('asistencias', 1);
    }

    public function test_lista_y_busca_alumnos(): void
    {
        $seccion = Seccion::factory()->create();
        Alumno::factory()->for($seccion)->create(['nombres' => 'Ana', 'apellidos' => 'Pérez']);
        Alumno::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Gómez']);

        $this->getJson('/api/v1/admin/alumnos')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/alumnos?q=ana')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/alumnos?seccion_id={$seccion->id}")->assertJsonCount(1, 'data');
    }

    public function test_crea_y_edita_personal(): void
    {
        $id = $this->postJson('/api/v1/admin/personal', [
            'codigo' => 'DOC01', 'nombres' => 'Luis', 'apellidos' => 'Ramos', 'tipo' => 'docente',
            'cargo' => 'Docente', 'correo' => 'luis@colegio.edu.pe',
        ])->assertCreated()->assertJsonPath('data.usuario', 'luis@colegio.edu.pe')->json('data.id');

        $this->assertSame(Rol::Personal, User::query()->where('usuario', 'luis@colegio.edu.pe')->value('rol'));

        $this->putJson("/api/v1/admin/personal/{$id}", [
            'codigo' => 'DOC01', 'nombres' => 'Luis', 'apellidos' => 'Ramos', 'tipo' => 'administrativo', 'activo' => false,
        ])->assertOk()->assertJsonPath('data.tipo', 'administrativo');

        $this->assertFalse(Personal::query()->findOrFail($id)->user->activo);
    }

    public function test_crea_un_apoderado_con_su_telefono_como_usuario_y_sus_hijos(): void
    {
        $hijos = Alumno::factory()->count(2)->create();

        $this->postJson('/api/v1/admin/apoderados', [
            'nombres' => 'María Pérez', 'telefono' => '+51 987-654-321',
            'hijos' => [
                ['alumno_id' => $hijos[0]->id, 'parentesco' => 'madre'],
                ['alumno_id' => $hijos[1]->id],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.telefono', '987654321')
            ->assertJsonPath('data.debe_cambiar_clave', true)
            ->assertJsonCount(2, 'data.hijos');

        $this->assertDatabaseHas('users', ['usuario' => '987654321', 'rol' => 'apoderado']);
        $this->assertDatabaseHas('alumno_apoderado', ['alumno_id' => $hijos[0]->id, 'parentesco' => 'madre']);
        $this->assertDatabaseHas('alumno_apoderado', ['alumno_id' => $hijos[1]->id, 'parentesco' => 'apoderado']);
    }

    public function test_no_admite_dos_apoderados_con_el_mismo_telefono(): void
    {
        Apoderado::factory()->create(['telefono' => '987654321']);

        $this->postJson('/api/v1/admin/apoderados', ['nombres' => 'Otro', 'telefono' => '987 654 321'])
            ->assertUnprocessable()->assertJsonValidationErrors('telefono');
    }

    public function test_vincula_y_desvincula_hijos(): void
    {
        $apoderado = Apoderado::factory()->create();
        $alumno = Alumno::factory()->create();

        $this->postJson("/api/v1/admin/apoderados/{$apoderado->id}/hijos", ['alumno_id' => $alumno->id, 'parentesco' => 'padre'])
            ->assertOk()->assertJsonCount(1, 'data.hijos');

        $this->deleteJson("/api/v1/admin/apoderados/{$apoderado->id}/hijos/{$alumno->id}")
            ->assertOk()->assertJsonCount(0, 'data.hijos');
    }

    public function test_restablecer_la_clave_vuelve_a_la_inicial_y_cierra_sesiones(): void
    {
        $apoderado = Apoderado::factory()->create();
        $apoderado->user->createToken('telefono');

        $this->postJson("/api/v1/admin/apoderados/{$apoderado->id}/restablecer-clave")->assertOk();

        $cuenta = $apoderado->user->fresh();
        $this->assertTrue($cuenta->debe_cambiar_clave);
        $this->assertTrue(Hash::check('123456', $cuenta->password));
        $this->assertSame(0, $cuenta->tokens()->count());
    }

    public function test_registrar_un_permiso_avisa_al_apoderado(): void
    {
        $alumno = Alumno::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez']);
        $apoderado = Apoderado::factory()->create();
        $apoderado->hijos()->attach($alumno);

        $id = $this->postJson('/api/v1/admin/permisos', [
            'alumno_id' => $alumno->id, 'tipo' => 'salida_anticipada', 'fecha' => '2026-03-12',
            'hora' => '11:30', 'motivo' => 'Cita médica',
        ])
            ->assertCreated()
            ->assertJsonPath('data.tipo_etiqueta', 'Salida anticipada')
            ->assertJsonPath('data.hora', '11:30')
            ->assertJsonPath('apoderados_avisados', 1)
            ->json('data.id');

        $this->assertDatabaseHas('mensajes', [
            'apoderado_id' => $apoderado->id, 'tipo' => 'permiso', 'titulo' => 'Permiso: Salida anticipada',
            'cuerpo' => 'Ana Pérez tiene permiso de salida anticipada el 12/03/2026 a las 11:30. Motivo: Cita médica',
        ]);

        // Anularlo avisa una sola vez, aunque se repita la petición.
        $this->deleteJson("/api/v1/admin/permisos/{$id}")->assertOk();
        $this->deleteJson("/api/v1/admin/permisos/{$id}")->assertOk();

        $this->assertDatabaseHas('mensajes', ['titulo' => 'Permiso anulado']);
        $this->assertDatabaseCount('mensajes', 2);
    }

    public function test_el_permiso_exige_hora_salvo_en_la_inasistencia(): void
    {
        $alumno = Alumno::factory()->create();

        $this->postJson('/api/v1/admin/permisos', [
            'alumno_id' => $alumno->id, 'tipo' => 'ingreso_tarde', 'fecha' => '2026-03-12', 'motivo' => 'Trámite',
        ])->assertUnprocessable()->assertJsonValidationErrors('hora');

        $this->postJson('/api/v1/admin/permisos', [
            'alumno_id' => $alumno->id, 'tipo' => 'inasistencia', 'fecha' => '2026-03-12', 'motivo' => 'Viaje',
        ])->assertCreated()->assertJsonPath('apoderados_avisados', 0);
    }
}
