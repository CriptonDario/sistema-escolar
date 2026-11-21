<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Asignacion;
use App\Models\Grado;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\Personal;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocenteTest extends TestCase
{
    use RefreshDatabase;

    private Personal $docente;

    private Seccion $aula;

    private Seccion $otraAula;

    private Materia $matematica;

    private Materia $arte;

    private Alumno $ana;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo('2026-04-06 09:00:00');

        $this->aula = Seccion::factory()->for(Grado::factory()->create(['nivel' => 'secundaria', 'nombre' => '2do Secundaria']))->create(['nombre' => 'B']);
        $this->otraAula = Seccion::factory()->create();
        $this->matematica = Materia::query()->create(['nombre' => 'Matemática']);
        $this->arte = Materia::query()->create(['nombre' => 'Arte']);
        $this->ana = Alumno::factory()->for($this->aula)->create(['nombres' => 'Ana', 'apellidos' => 'Pérez']);

        $this->docente = Personal::factory()->create([
            'nombres' => 'Luis', 'apellidos' => 'Ramos',
            'user_id' => User::factory()->rol(Rol::Personal)->create()->id,
        ]);
        Asignacion::query()->create(['personal_id' => $this->docente->id, 'seccion_id' => $this->aula->id, 'materia_id' => $this->matematica->id, 'anio' => 2026]);
    }

    private function comoDocente(): void
    {
        Sanctum::actingAs($this->docente->user);
    }

    public function test_el_admin_asigna_docentes_y_no_repite_materia_en_un_aula(): void
    {
        $this->actingAs(User::factory()->create());
        $otro = Personal::factory()->create();
        $base = ['seccion_id' => $this->aula->id, 'anio' => 2026];

        $this->get('/asignaciones')->assertOk()->assertSee('Ramos, Luis')->assertSee('Matemática');

        $this->post('/asignaciones', $base + ['personal_id' => $otro->id, 'materia_id' => $this->matematica->id])->assertSessionHasErrors('materia_id');
        $this->post('/asignaciones', $base + ['personal_id' => $otro->id, 'materia_id' => $this->arte->id])->assertSessionHas('ok');

        $asignacion = Asignacion::query()->where('personal_id', $otro->id)->firstOrFail();
        $this->delete("/asignaciones/{$asignacion->id}")->assertSessionHas('ok');
        $this->assertSame(1, Asignacion::query()->count());
    }

    public function test_el_docente_ve_solo_sus_aulas_de_este_anio(): void
    {
        Asignacion::query()->create(['personal_id' => $this->docente->id, 'seccion_id' => $this->otraAula->id, 'materia_id' => $this->arte->id, 'anio' => 2025]);
        $this->comoDocente();

        $this->getJson('/api/v1/docente/aulas')->assertOk()
            ->assertJsonCount(1, 'aulas')
            ->assertJsonPath('aulas.0.aula', '2do Secundaria B')
            ->assertJsonPath('aulas.0.materia', 'Matemática');
    }

    public function test_registra_y_consulta_las_notas_de_su_aula(): void
    {
        $this->comoDocente();
        $ruta = "/api/v1/docente/aulas/{$this->aula->id}/notas";

        $this->getJson("{$ruta}?materia_id={$this->matematica->id}&periodo=1")->assertOk()
            ->assertJsonPath('escala', 'numerica')
            ->assertJsonPath('alumnos.0.nombre', 'Pérez, Ana')
            ->assertJsonPath('alumnos.0.nota', null);

        $this->postJson($ruta, ['materia_id' => $this->matematica->id, 'periodo' => 1, 'notas' => [$this->ana->id => 17.5]])
            ->assertOk()->assertJsonPath('guardadas', 1);

        $this->getJson("{$ruta}?materia_id={$this->matematica->id}&periodo=1")->assertJsonPath('alumnos.0.nota', 17.5);
        $this->assertDatabaseHas('notas', ['alumno_id' => $this->ana->id, 'nota' => 17.5, 'registrado_por' => $this->docente->user_id]);

        $this->postJson($ruta, ['materia_id' => $this->matematica->id, 'periodo' => 1, 'notas' => [$this->ana->id => 25]])->assertUnprocessable();
    }

    public function test_no_puede_calificar_lo_que_no_le_asignaron(): void
    {
        $this->comoDocente();
        $ajeno = Alumno::factory()->for($this->otraAula)->create();

        // Otra materia en su aula, y su materia en otra aula.
        $this->postJson("/api/v1/docente/aulas/{$this->aula->id}/notas", ['materia_id' => $this->arte->id, 'periodo' => 1, 'notas' => [$this->ana->id => 15]])
            ->assertForbidden();
        $this->postJson("/api/v1/docente/aulas/{$this->otraAula->id}/notas", ['materia_id' => $this->matematica->id, 'periodo' => 1, 'notas' => [$ajeno->id => 15]])
            ->assertForbidden();
        $this->getJson("/api/v1/docente/aulas/{$this->otraAula->id}/notas?materia_id={$this->matematica->id}")->assertForbidden();

        // Un alumno de otra aula metido en la lista de la suya se ignora.
        $this->postJson("/api/v1/docente/aulas/{$this->aula->id}/notas", ['materia_id' => $this->matematica->id, 'periodo' => 1, 'notas' => [$ajeno->id => 20]])
            ->assertOk()->assertJsonPath('guardadas', 0);

        $this->assertDatabaseCount('notas', 0);
    }

    public function test_publica_una_tarea_y_registra_conducta_avisando_al_apoderado(): void
    {
        $apoderado = Apoderado::factory()->create();
        $apoderado->hijos()->attach($this->ana);
        $this->comoDocente();

        $this->postJson("/api/v1/docente/aulas/{$this->aula->id}/tareas", [
            'materia_id' => $this->matematica->id, 'tipo' => 'tarea', 'titulo' => 'Ejercicios pág. 40', 'fecha_entrega' => '2026-04-10',
        ])->assertCreated();
        $this->assertDatabaseHas('mensajes', ['apoderado_id' => $apoderado->id, 'tipo' => 'tarea', 'titulo' => 'Tarea de Matemática']);

        $this->postJson("/api/v1/docente/aulas/{$this->aula->id}/tareas", [
            'materia_id' => $this->matematica->id, 'tipo' => 'tarea', 'titulo' => 'Pasada', 'fecha_entrega' => '2026-04-01',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/docente/conducta', ['alumno_id' => $this->ana->id, 'tipo' => 'merito', 'descripcion' => 'Participó muy bien'])
            ->assertCreated();
        $this->assertDatabaseHas('conductas', ['alumno_id' => $this->ana->id, 'fecha' => '2026-04-06', 'registrado_por' => $this->docente->user_id]);
        $this->assertDatabaseHas('mensajes', ['tipo' => 'conducta', 'cuerpo' => 'Ana Pérez: Participó muy bien']);

        $ajeno = Alumno::factory()->for($this->otraAula)->create();
        $this->postJson('/api/v1/docente/conducta', ['alumno_id' => $ajeno->id, 'tipo' => 'demerito', 'descripcion' => 'X'])->assertForbidden();
    }

    public function test_ve_su_horario(): void
    {
        Horario::query()->create(['seccion_id' => $this->aula->id, 'dia' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '08:45', 'materia_id' => $this->matematica->id, 'personal_id' => $this->docente->id]);
        Horario::query()->create(['seccion_id' => $this->aula->id, 'dia' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '09:45', 'materia_id' => $this->arte->id]);
        $this->comoDocente();

        $this->getJson('/api/v1/docente/horario')->assertOk()
            ->assertJsonCount(1, 'dias.0.bloques')
            ->assertJsonPath('dias.0.bloques.0.nombre', 'Matemática')
            ->assertJsonPath('dias.0.bloques.0.aula', '2do Secundaria B');
    }

    public function test_las_funciones_de_docente_son_solo_para_cuentas_de_personal(): void
    {
        foreach ([Rol::Apoderado, Rol::Alumno, Rol::Guardia] as $rol) {
            $this->comoRol($rol);
            $this->getJson('/api/v1/docente/aulas')->assertForbidden();
        }
    }
}
