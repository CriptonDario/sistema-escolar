<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Jobs\EnviarPush;
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

class AsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private Alumno $alumno;

    private Apoderado $apoderado;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $seccion = Seccion::factory()
            ->for(Grado::factory()->create(['nombre' => '2do Secundaria', 'nivel' => 'secundaria']))
            ->create(['nombre' => 'B']);

        $this->alumno = Alumno::factory()->for($seccion)->create(['codigo' => '2SB01', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
        $this->apoderado = Apoderado::factory()->create();
        $this->apoderado->hijos()->attach($this->alumno, ['parentesco' => 'madre']);
    }

    public function test_el_primer_escaneo_registra_la_entrada_puntual_y_avisa_al_apoderado(): void
    {
        $this->comoRol(Rol::Guardia);
        $this->travelTo('2026-03-10 07:20:00');

        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01'])
            ->assertOk()
            ->assertJsonPath('evento', 'entrada')
            ->assertJsonPath('registrado', true)
            ->assertJsonPath('entrada', '07:20')
            ->assertJsonPath('persona.tipo', 'alumno')
            ->assertJsonPath('persona.nombre', 'Ana Pérez')
            ->assertJsonPath('persona.detalle', '2do Secundaria B');

        $this->assertDatabaseHas('asistencias', [
            'persona_type' => 'alumno', 'persona_id' => $this->alumno->id, 'estado' => 'puntual',
        ]);
        $this->assertDatabaseHas('mensajes', [
            'apoderado_id' => $this->apoderado->id, 'alumno_id' => $this->alumno->id, 'tipo' => 'entrada',
            'titulo' => 'Ingresó al colegio',
            'cuerpo' => 'Ana Pérez ingresó al colegio a las 07:20 (10/03/2026).',
        ]);
        Queue::assertPushed(EnviarPush::class, fn (EnviarPush $job) => $job->userId === $this->apoderado->user_id);
    }

    public function test_el_minuto_de_la_hora_de_entrada_cuenta_entero_como_puntual(): void
    {
        $this->comoRol(Rol::Guardia);

        $this->travelTo('2026-03-10 07:30:59');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01'])->assertJsonPath('evento', 'entrada');

        $otro = Alumno::factory()->create(['codigo' => 'OTRO1']);
        $this->travelTo('2026-03-10 07:31:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => 'OTRO1'])->assertJsonPath('evento', 'tardanza');
        $this->assertDatabaseHas('asistencias', ['persona_id' => $otro->id, 'estado' => 'tarde']);
    }

    public function test_la_tolerancia_configurada_retrasa_la_tardanza(): void
    {
        $this->comoRol(Rol::Guardia);
        app(Ajustes::class)->guardar('tolerancia_minutos', '10');

        $this->travelTo('2026-03-10 07:38:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01'])->assertJsonPath('evento', 'entrada');
    }

    public function test_la_tardanza_avisa_como_tardanza(): void
    {
        $this->comoRol(Rol::Guardia);
        $this->travelTo('2026-03-10 08:05:00');

        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01'])->assertJsonPath('evento', 'tardanza');

        $this->assertDatabaseHas('mensajes', ['tipo' => 'tardanza', 'titulo' => 'Llegó tarde al colegio']);
    }

    public function test_un_doble_escaneo_en_la_puerta_no_cuenta_como_salida(): void
    {
        $this->comoRol(Rol::Guardia);

        $this->travelTo('2026-03-10 07:20:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01']);

        $this->travelTo('2026-03-10 07:21:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01'])
            ->assertOk()
            ->assertJsonPath('evento', 'entrada_ya_registrada')
            ->assertJsonPath('registrado', false)
            ->assertJsonPath('salida', null);

        $this->assertDatabaseCount('mensajes', 1);
    }

    public function test_el_segundo_escaneo_del_dia_registra_la_salida_y_avisa(): void
    {
        $this->comoRol(Rol::Guardia);

        $this->travelTo('2026-03-10 07:20:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01']);

        $this->travelTo('2026-03-10 13:05:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01'])
            ->assertOk()
            ->assertJsonPath('evento', 'salida')
            ->assertJsonPath('salida', '13:05');

        $this->assertDatabaseHas('mensajes', [
            'tipo' => 'salida', 'cuerpo' => 'Ana Pérez salió del colegio a las 13:05 (10/03/2026).',
        ]);

        // Un tercer escaneo ya no cambia nada ni vuelve a avisar.
        $this->travelTo('2026-03-10 13:06:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01'])
            ->assertJsonPath('evento', 'salida_ya_registrada');
        $this->assertDatabaseCount('mensajes', 2);
        $this->assertDatabaseCount('asistencias', 1);
    }

    public function test_al_dia_siguiente_vuelve_a_ser_entrada(): void
    {
        $this->comoRol(Rol::Guardia);

        $this->travelTo('2026-03-10 07:20:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01']);

        $this->travelTo('2026-03-11 07:25:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01'])->assertJsonPath('evento', 'entrada');

        $this->assertDatabaseCount('asistencias', 2);
    }

    public function test_tambien_marca_al_personal_y_no_genera_mensajes(): void
    {
        $this->comoRol(Rol::Admin);
        Personal::factory()->create(['codigo' => 'DOC01', 'nombres' => 'Luis', 'apellidos' => 'Ramos', 'cargo' => 'Docente de Matemática']);
        $this->travelTo('2026-03-10 07:10:00');

        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => 'DOC01'])
            ->assertOk()
            ->assertJsonPath('evento', 'entrada')
            ->assertJsonPath('persona.tipo', 'personal')
            ->assertJsonPath('persona.detalle', 'Docente de Matemática');

        $this->assertDatabaseHas('asistencias', ['persona_type' => 'personal', 'estado' => 'puntual']);
        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_rechaza_codigos_inexistentes_inactivos_o_repetidos(): void
    {
        $this->comoRol(Rol::Guardia);
        Alumno::factory()->create(['codigo' => 'BAJA1', 'activo' => false]);
        Alumno::factory()->create(['codigo' => 'DUP01']);
        Personal::factory()->create(['codigo' => 'DUP01']);

        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => 'NOEXISTE'])
            ->assertUnprocessable()->assertJsonPath('codigo', 'codigo_no_reconocido');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => 'BAJA1'])->assertUnprocessable();
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => 'DUP01'])->assertUnprocessable();

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_no_avisa_a_un_apoderado_con_la_cuenta_desactivada(): void
    {
        $this->comoRol(Rol::Guardia);
        $this->apoderado->user->update(['activo' => false]);

        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01'])->assertOk();

        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_solo_admin_y_guardia_pueden_marcar(): void
    {
        foreach ([Rol::Apoderado, Rol::Alumno, Rol::Personal, Rol::Direccion] as $rol) {
            $this->comoRol($rol);
            $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01'])->assertForbidden();
        }

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_resumen_del_dia(): void
    {
        $this->comoRol(Rol::Guardia);
        Personal::factory()->create(['codigo' => 'DOC01']);
        Alumno::factory()->create(['codigo' => 'ALU02']);

        $this->travelTo('2026-03-10 07:20:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01']);
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => 'DOC01']);
        $this->travelTo('2026-03-10 08:00:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => 'ALU02']);

        $this->getJson('/api/v1/asistencia/hoy')
            ->assertOk()
            ->assertJsonPath('fecha', '2026-03-10')
            ->assertJsonPath('alumnos', ['total' => 2, 'presentes' => 2, 'tardanzas' => 1, 'salieron' => 0])
            ->assertJsonPath('personal', ['total' => 1, 'presentes' => 1, 'tardanzas' => 0, 'salieron' => 0])
            ->assertJsonCount(3, 'ultimas');
    }

    public function test_busca_personas_por_nombre_o_codigo_para_marcar_a_mano(): void
    {
        $this->comoRol(Rol::Guardia);
        Personal::factory()->create(['codigo' => 'DOC01', 'nombres' => 'Ana', 'apellidos' => 'Torres']);
        Alumno::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Gómez']);

        $this->getJson('/api/v1/asistencia/personas?q=ana')
            ->assertOk()
            ->assertJsonCount(2, 'personas')
            ->assertJsonPath('personas.0.tipo', 'alumno')
            ->assertJsonPath('personas.0.detalle', '2do Secundaria B');

        $this->getJson('/api/v1/asistencia/personas?q='.urlencode('pérez ana'))->assertJsonCount(1, 'personas');
        $this->getJson('/api/v1/asistencia/personas?q=2SB')->assertJsonCount(1, 'personas');
        $this->getJson('/api/v1/asistencia/personas?q=a')->assertUnprocessable();
    }

    public function test_el_alumno_y_el_personal_ven_su_propia_asistencia(): void
    {
        $cuenta = User::factory()->rol(Rol::Alumno)->create();
        $this->alumno->update(['user_id' => $cuenta->id]);

        $this->comoRol(Rol::Guardia);
        $this->travelTo('2026-03-10 07:20:00');
        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => '2SB01']);

        $this->actingAs($cuenta, 'sanctum');
        $this->getJson('/api/v1/mi/asistencia?mes=2026-03')
            ->assertOk()
            ->assertJsonPath('mes', '2026-03')
            ->assertJsonCount(1, 'dias')
            ->assertJsonPath('dias.0.entrada', '07:20');

        $this->getJson('/api/v1/mi/asistencia?mes=2026-02')->assertJsonCount(0, 'dias');
    }
}
