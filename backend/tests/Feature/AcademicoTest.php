<?php

namespace Tests\Feature;

use App\Enums\EscalaCalificacion;
use App\Enums\Nivel;
use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Grado;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\Personal;
use App\Models\Seccion;
use App\Models\User;
use App\Support\Ajustes;
use App\Support\Escalas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicoTest extends TestCase
{
    use RefreshDatabase;

    private Seccion $aula;

    private Alumno $ana;

    private Apoderado $apoderado;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo('2026-03-10 09:00:00');

        $this->aula = Seccion::factory()->for(Grado::factory()->create(['nivel' => 'secundaria', 'nombre' => '2do Secundaria']))->create(['nombre' => 'B']);
        $this->ana = Alumno::factory()->for($this->aula)->create(['nombres' => 'Ana', 'apellidos' => 'Pérez']);
        $this->apoderado = Apoderado::factory()->create();
        $this->apoderado->hijos()->attach($this->ana);

        $this->actingAs(User::factory()->create());
    }

    public function test_materias_alta_edicion_y_duplicados(): void
    {
        $this->post('/materias', ['nombre' => 'Matemática', 'nivel' => ''])->assertSessionHas('ok');
        $this->post('/materias', ['nombre' => 'Matemática', 'nivel' => ''])->assertSessionHasErrors('nombre');
        $this->post('/materias', ['nombre' => 'Matemática', 'nivel' => 'primaria'])->assertSessionHas('ok');

        $materia = Materia::query()->whereNull('nivel')->firstOrFail();
        $this->put("/materias/{$materia->id}", ['nombre' => 'Álgebra', 'nivel' => 'secundaria'])->assertSessionHas('ok');
        $this->post("/materias/{$materia->id}/alternar");

        $this->assertSame('Álgebra', $materia->fresh()->nombre);
        $this->assertFalse($materia->fresh()->activo);
        $this->get('/materias')->assertOk()->assertSee('Álgebra');
    }

    public function test_matricula_asigna_el_aula_y_no_se_repite_en_el_anio(): void
    {
        $otraAula = Seccion::factory()->create();

        $this->get('/matriculas')->assertOk()->assertSee('Pérez, Ana')->assertSee('1 sin matricular');

        $this->post('/matriculas', ['alumno_id' => $this->ana->id, 'seccion_id' => $otraAula->id, 'anio' => 2026, 'fecha' => '2026-03-01'])
            ->assertSessionHas('ok');

        $this->assertSame($otraAula->id, $this->ana->fresh()->seccion_id);
        $this->post('/matriculas', ['alumno_id' => $this->ana->id, 'seccion_id' => $otraAula->id, 'anio' => 2026, 'fecha' => '2026-03-01'])
            ->assertSessionHasErrors('anio');

        // Matricular para otro año no cambia el aula actual.
        $this->post('/matriculas', ['alumno_id' => $this->ana->id, 'seccion_id' => $this->aula->id, 'anio' => 2027, 'fecha' => '2026-12-01'])
            ->assertSessionHas('ok');
        $this->assertSame($otraAula->id, $this->ana->fresh()->seccion_id);

        $matricula = Matricula::query()->where('anio', 2026)->firstOrFail();
        $this->put("/matriculas/{$matricula->id}", ['estado' => 'retirado', 'observacion' => 'Viaje'])->assertSessionHas('ok');
        $this->assertSame('retirado', $matricula->fresh()->estado->value);
        $this->get('/matriculas')->assertSee('Todos los alumnos activos ya están matriculados');
    }

    public function test_registra_corrige_y_borra_notas_solo_de_alumnos_del_aula(): void
    {
        $materia = Materia::query()->create(['nombre' => 'Matemática']);
        $ajeno = Alumno::factory()->create();
        $base = ['anio' => 2026, 'seccion_id' => $this->aula->id, 'materia_id' => $materia->id, 'periodo' => 1];

        $this->get("/notas?seccion_id={$this->aula->id}&materia_id={$materia->id}&periodo=1")
            ->assertOk()->assertSee('Pérez, Ana')->assertSee('Trimestre 1');

        $this->post('/notas', $base + ['notas' => [$this->ana->id => '15.5', $ajeno->id => '20']])->assertSessionHas('ok');
        $this->assertDatabaseHas('notas', ['alumno_id' => $this->ana->id, 'nota' => 15.5, 'periodo' => 1]);
        $this->assertDatabaseMissing('notas', ['alumno_id' => $ajeno->id]);

        $this->post('/notas', $base + ['notas' => [$this->ana->id => '18']]);
        $this->assertSame(1, Nota::query()->count());
        $this->assertSame(18.0, Nota::query()->first()->nota);

        $this->post('/notas', $base + ['notas' => [$this->ana->id => '21']])->assertSessionHasErrors('notas.'.$this->ana->id);
        $this->post('/notas', $base + ['notas' => [$this->ana->id => '']]);
        $this->assertSame(0, Nota::query()->count());
    }

    public function test_primaria_califica_con_letras_por_defecto(): void
    {
        $aula = Seccion::factory()->for(Grado::factory()->create(['nivel' => 'primaria', 'nombre' => '1ro Primaria']))->create();
        $luis = Alumno::factory()->for($aula)->create(['nombres' => 'Luis', 'apellidos' => 'Rojas']);
        $this->apoderado->hijos()->attach($luis);
        $materia = Materia::query()->create(['nombre' => 'Personal Social']);
        $base = ['anio' => 2026, 'seccion_id' => $aula->id, 'materia_id' => $materia->id, 'periodo' => 1];

        $this->get("/notas?seccion_id={$aula->id}&materia_id={$materia->id}")
            ->assertOk()->assertSee('AD — Logro destacado')->assertSee('Escala literal');

        $this->post('/notas', $base + ['notas' => [$luis->id => 'AD']])->assertSessionHas('ok');
        $this->assertDatabaseHas('notas', ['alumno_id' => $luis->id, 'letra' => 'AD', 'nota' => null]);

        // En escala literal no se aceptan números ni letras fuera de la escala.
        $this->post('/notas', $base + ['notas' => [$luis->id => '18']])->assertSessionHasErrors('notas.'.$luis->id);
        $this->post('/notas', $base + ['notas' => [$luis->id => 'Z']])->assertSessionHasErrors('notas.'.$luis->id);

        Sanctum::actingAs($this->apoderado->user);
        $this->getJson("/api/v1/hijos/{$luis->id}/notas")->assertOk()
            ->assertJsonPath('escala', 'literal')
            ->assertJsonPath('letras.AD', 'Logro destacado')
            ->assertJsonPath('materias.0.periodos.1', 'AD')
            ->assertJsonPath('materias.0.promedio', null);
    }

    public function test_el_colegio_elige_la_escala_de_cada_nivel(): void
    {
        $materia = Materia::query()->create(['nombre' => 'Matemática']);
        $base = ['anio' => 2026, 'seccion_id' => $this->aula->id, 'materia_id' => $materia->id, 'periodo' => 1];

        // Secundaria es numérica por defecto: no acepta letras.
        $this->post('/notas', $base + ['notas' => [$this->ana->id => 'A']])->assertSessionHasErrors('notas.'.$this->ana->id);
        $this->post('/notas', $base + ['notas' => [$this->ana->id => '16']])->assertSessionHas('ok');

        app(Escalas::class)->guardar(Nivel::Secundaria, EscalaCalificacion::Literal);

        $this->post('/notas', $base + ['notas' => [$this->ana->id => 'A']])->assertSessionHas('ok');
        // Al cambiar de escala, la nota numérica anterior se reemplaza por la letra.
        $this->assertDatabaseHas('notas', ['alumno_id' => $this->ana->id, 'letra' => 'A', 'nota' => null]);
        $this->assertSame(1, Nota::query()->count());
    }

    public function test_los_periodos_de_evaluacion_son_configurables(): void
    {
        $materia = Materia::query()->create(['nombre' => 'Matemática']);
        $base = ['anio' => 2026, 'seccion_id' => $this->aula->id, 'materia_id' => $materia->id, 'notas' => [$this->ana->id => '12']];

        // Por defecto, trimestres: no existe un 4.º periodo.
        $this->post('/notas', $base + ['periodo' => 4])->assertSessionHasErrors('periodo');

        app(Ajustes::class)->guardar('periodos_por_anio', '2');
        $this->get('/notas')->assertSee('Semestre 1')->assertSee('Semestre 2')->assertDontSee('Trimestre');
        $this->post('/notas', $base + ['periodo' => 3])->assertSessionHasErrors('periodo');
        $this->post('/notas', $base + ['periodo' => 2])->assertSessionHas('ok');

        app(Ajustes::class)->guardar('periodos_por_anio', '4');
        $this->get('/notas')->assertSee('Bimestre 4');
    }

    public function test_comunicado_llega_una_vez_a_cada_apoderado_del_alcance(): void
    {
        // Segundo hijo del mismo apoderado en la misma aula: no debe duplicar.
        $hermano = Alumno::factory()->for($this->aula)->create();
        $this->apoderado->hijos()->attach($hermano);
        // Otra familia en primaria.
        $primaria = Seccion::factory()->for(Grado::factory()->create(['nivel' => 'primaria']))->create();
        $otro = Apoderado::factory()->create();
        $otro->hijos()->attach(Alumno::factory()->for($primaria)->create());

        $this->post('/comunicados', ['titulo' => 'Reunión', 'cuerpo' => 'Viernes 6 p. m.', 'alcance' => 'seccion', 'seccion_id' => $this->aula->id])
            ->assertSessionHas('ok');
        $this->assertDatabaseCount('mensajes', 1);
        $this->assertDatabaseHas('mensajes', ['apoderado_id' => $this->apoderado->id, 'tipo' => 'comunicado', 'titulo' => 'Reunión']);

        $this->post('/comunicados', ['titulo' => 'Primaria', 'cuerpo' => 'Texto', 'alcance' => 'nivel', 'nivel' => 'primaria']);
        $this->assertDatabaseHas('mensajes', ['apoderado_id' => $otro->id, 'titulo' => 'Primaria']);
        $this->assertDatabaseMissing('mensajes', ['apoderado_id' => $this->apoderado->id, 'titulo' => 'Primaria']);

        $this->post('/comunicados', ['titulo' => 'General', 'cuerpo' => 'Texto', 'alcance' => 'todos']);
        $this->assertDatabaseCount('mensajes', 4);

        $this->post('/comunicados', ['titulo' => 'X', 'cuerpo' => 'Y', 'alcance' => 'seccion'])->assertSessionHasErrors('seccion_id');
        $this->get('/comunicados')->assertOk()->assertSee('Reunión')->assertSee('2do Secundaria B')->assertSee('Todo el colegio');
    }

    public function test_horario_agrega_bloques_y_rechaza_cruces(): void
    {
        $materia = Materia::query()->create(['nombre' => 'Matemática']);
        $docente = Personal::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Ramos']);
        $base = ['seccion_id' => $this->aula->id, 'dia' => 1];

        $this->post('/horarios', $base + ['hora_inicio' => '08:00', 'hora_fin' => '08:45', 'materia_id' => $materia->id, 'personal_id' => $docente->id])
            ->assertSessionHas('ok');
        $this->post('/horarios', $base + ['hora_inicio' => '08:30', 'hora_fin' => '09:15', 'materia_id' => $materia->id])->assertSessionHas('error');
        $this->post('/horarios', $base + ['hora_inicio' => '08:45', 'hora_fin' => '09:00', 'titulo' => 'Recreo'])->assertSessionHas('ok');
        $this->post('/horarios', $base + ['hora_inicio' => '10:00', 'hora_fin' => '09:00', 'titulo' => 'X'])->assertSessionHasErrors('hora_fin');
        $this->post('/horarios', $base + ['hora_inicio' => '11:00', 'hora_fin' => '12:00'])->assertSessionHasErrors('titulo');

        $this->get("/horarios?seccion_id={$this->aula->id}")->assertOk()
            ->assertSee('08:00–08:45')->assertSee('Matemática')->assertSee('Luis Ramos')->assertSee('Recreo');

        $bloque = Horario::query()->where('titulo', 'Recreo')->firstOrFail();
        $this->delete("/horarios/{$bloque->id}")->assertSessionHas('ok');
        $this->assertSame(1, Horario::query()->count());
    }

    public function test_tarea_y_conducta_avisan_al_apoderado(): void
    {
        $materia = Materia::query()->create(['nombre' => 'Comunicación']);

        $this->post('/tareas', [
            'seccion_id' => $this->aula->id, 'materia_id' => $materia->id, 'tipo' => 'examen',
            'titulo' => 'Comprensión lectora', 'fecha_entrega' => '2026-03-20',
        ])->assertSessionHas('ok');
        $this->assertDatabaseHas('mensajes', [
            'apoderado_id' => $this->apoderado->id, 'tipo' => 'tarea', 'titulo' => 'Examen de Comunicación',
            'cuerpo' => 'Ana: Comprensión lectora. Fecha: 20/03/2026.',
        ]);

        $this->post('/conducta', ['alumno_id' => $this->ana->id, 'tipo' => 'merito', 'descripcion' => 'Ayudó a un compañero', 'fecha' => '2026-03-10'])
            ->assertSessionHas('ok');
        $this->assertDatabaseHas('mensajes', ['tipo' => 'conducta', 'titulo' => 'Mérito de conducta', 'cuerpo' => 'Ana Pérez: Ayudó a un compañero']);

        $this->post('/conducta', ['alumno_id' => $this->ana->id, 'tipo' => 'merito', 'descripcion' => 'X', 'fecha' => '2026-03-11'])
            ->assertSessionHasErrors('fecha'); // no se registra conducta a futuro

        $this->get('/tareas')->assertOk()->assertSee('Comprensión lectora');
        $this->get('/conducta')->assertOk()->assertSee('Ayudó a un compañero');
    }

    public function test_la_app_muestra_el_expediente_del_hijo(): void
    {
        $materia = Materia::query()->create(['nombre' => 'Matemática']);
        Nota::query()->create(['alumno_id' => $this->ana->id, 'materia_id' => $materia->id, 'anio' => 2026, 'periodo' => 1, 'nota' => 14]);
        Nota::query()->create(['alumno_id' => $this->ana->id, 'materia_id' => $materia->id, 'anio' => 2026, 'periodo' => 2, 'nota' => 17]);
        Horario::query()->create(['seccion_id' => $this->aula->id, 'dia' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '08:45', 'materia_id' => $materia->id]);
        $this->post('/tareas', ['seccion_id' => $this->aula->id, 'tipo' => 'tarea', 'titulo' => 'Ejercicios', 'fecha_entrega' => '2026-03-12']);
        $this->post('/conducta', ['alumno_id' => $this->ana->id, 'tipo' => 'demerito', 'descripcion' => 'Llegó sin uniforme', 'fecha' => '2026-03-09']);
        $this->post('/comunicados', ['titulo' => 'Aniversario', 'cuerpo' => 'Habrá actuación', 'alcance' => 'nivel', 'nivel' => 'secundaria']);
        $this->post('/comunicados', ['titulo' => 'Solo primaria', 'cuerpo' => 'Texto', 'alcance' => 'nivel', 'nivel' => 'primaria']);

        Sanctum::actingAs($this->apoderado->user);
        $id = $this->ana->id;

        $this->getJson("/api/v1/hijos/{$id}/notas")->assertOk()
            ->assertJsonPath('periodos.1', 'Trimestre 1')
            ->assertJsonPath('materias.0.materia', 'Matemática')
            ->assertJsonPath('materias.0.periodos.1', 14)
            ->assertJsonPath('materias.0.periodos.3', null)
            ->assertJsonPath('materias.0.promedio', 15.5);

        $this->getJson("/api/v1/hijos/{$id}/horario")->assertOk()
            ->assertJsonPath('dias.0.nombre', 'Lunes')->assertJsonPath('dias.0.bloques.0.inicio', '08:00')->assertJsonPath('dias.0.bloques.0.nombre', 'Matemática');
        $this->getJson("/api/v1/hijos/{$id}/tareas")->assertOk()->assertJsonCount(1, 'tareas')->assertJsonPath('tareas.0.titulo', 'Ejercicios');
        $this->getJson("/api/v1/hijos/{$id}/conducta")->assertOk()->assertJsonPath('registros.0.tipo_etiqueta', 'Demérito');
        $this->getJson("/api/v1/hijos/{$id}/comunicados")->assertOk()->assertJsonCount(1, 'comunicados')->assertJsonPath('comunicados.0.titulo', 'Aniversario');

        // Nunca el expediente de un alumno ajeno.
        $ajeno = Alumno::factory()->create();
        foreach (['notas', 'horario', 'tareas', 'conducta', 'comunicados'] as $recurso) {
            $this->getJson("/api/v1/hijos/{$ajeno->id}/{$recurso}")->assertForbidden();
        }
    }

    public function test_el_alumno_ve_su_propio_expediente_en_la_app(): void
    {
        $cuenta = User::factory()->rol(Rol::Alumno)->create();
        $this->ana->update(['user_id' => $cuenta->id]);
        $materia = Materia::query()->create(['nombre' => 'Arte']);
        Nota::query()->create(['alumno_id' => $this->ana->id, 'materia_id' => $materia->id, 'anio' => 2026, 'periodo' => 1, 'nota' => 19]);

        Sanctum::actingAs($cuenta);

        $this->getJson('/api/v1/mi/notas')->assertOk()->assertJsonPath('materias.0.materia', 'Arte')->assertJsonPath('materias.0.promedio', 19);
        $this->getJson('/api/v1/mi/horario')->assertOk()->assertJsonPath('dias', []);
        $this->getJson('/api/v1/mi/tareas')->assertOk();
        $this->getJson('/api/v1/mi/conducta')->assertOk();
        $this->getJson('/api/v1/mi/comunicados')->assertOk();
        $this->getJson("/api/v1/hijos/{$this->ana->id}/notas")->assertForbidden(); // esa ruta es del apoderado
    }

    public function test_solo_el_administrador_gestiona_lo_academico(): void
    {
        $this->actingAs(User::factory()->rol(Rol::Guardia)->create());

        foreach (['materias', 'matriculas', 'notas', 'horarios', 'tareas', 'conducta', 'comunicados'] as $pantalla) {
            $this->get("/{$pantalla}")->assertForbidden();
        }
    }
}
