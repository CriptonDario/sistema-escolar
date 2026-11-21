<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\TipoMensaje;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\User;
use App\Services\Mensajeria\Mensajero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApoderadoTest extends TestCase
{
    use RefreshDatabase;

    private Apoderado $apoderado;

    private Alumno $hijo;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->apoderado = Apoderado::factory()->create();
        $this->hijo = Alumno::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez']);
        $this->apoderado->hijos()->attach($this->hijo, ['parentesco' => 'padre']);

        Sanctum::actingAs($this->apoderado->user);
    }

    public function test_ve_a_sus_hijos_con_su_aula_y_el_estado_de_hoy(): void
    {
        Alumno::factory()->create(); // hijo de otra familia
        $this->hijo->asistencias()->create(['fecha' => today(), 'hora_entrada' => '07:25:00', 'estado' => 'puntual']);

        $this->getJson('/api/v1/hijos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombres', 'Ana')
            ->assertJsonPath('data.0.parentesco', 'padre')
            ->assertJsonPath('data.0.hoy.entrada', '07:25')
            ->assertJsonPath('data.0.hoy.estado', 'puntual');
    }

    public function test_no_puede_ver_la_asistencia_de_un_alumno_ajeno(): void
    {
        $ajeno = Alumno::factory()->create();

        $this->getJson("/api/v1/hijos/{$ajeno->id}/asistencia")->assertForbidden();
        $this->getJson("/api/v1/hijos/{$this->hijo->id}/asistencia")->assertOk();
    }

    public function test_asistencia_y_permisos_del_mes(): void
    {
        $this->hijo->asistencias()->create(['fecha' => '2026-03-10', 'hora_entrada' => '07:25:00', 'estado' => 'puntual']);
        $this->hijo->asistencias()->create(['fecha' => '2026-04-01', 'hora_entrada' => '07:25:00', 'estado' => 'puntual']);
        $this->hijo->permisos()->create(['tipo' => 'inasistencia', 'fecha' => '2026-03-12', 'motivo' => 'Cita médica']);
        $this->hijo->permisos()->create(['tipo' => 'inasistencia', 'fecha' => '2026-03-13', 'motivo' => 'Anulado', 'anulado_en' => now()]);

        $this->getJson("/api/v1/hijos/{$this->hijo->id}/asistencia?mes=2026-03")
            ->assertOk()
            ->assertJsonCount(1, 'dias')
            ->assertJsonCount(1, 'permisos')
            ->assertJsonPath('permisos.0.motivo', 'Cita médica');
    }

    public function test_bandeja_de_mensajes_con_no_leidos_y_paginacion(): void
    {
        $mensajero = app(Mensajero::class);
        foreach (range(1, 5) as $n) {
            $mensajero->enviar($this->apoderado, TipoMensaje::Entrada, "Mensaje {$n}", 'Texto', $this->hijo);
        }

        $pagina = $this->getJson('/api/v1/mensajes?limite=3')
            ->assertOk()
            ->assertJsonPath('no_leidos', 5)
            ->assertJsonCount(3, 'mensajes')
            ->assertJsonPath('mensajes.0.titulo', 'Mensaje 5')
            ->assertJsonPath('mensajes.0.alumno', 'Ana Pérez')
            ->assertJsonPath('mensajes.0.leido', false);

        $ultimoId = $pagina->json('mensajes.2.id');
        $this->getJson("/api/v1/mensajes?limite=3&antes_de={$ultimoId}")->assertJsonCount(2, 'mensajes');
    }

    public function test_marca_mensajes_como_leidos(): void
    {
        $mensajero = app(Mensajero::class);
        $uno = $mensajero->enviar($this->apoderado, TipoMensaje::Entrada, 'Uno', 'Texto');
        $mensajero->enviar($this->apoderado, TipoMensaje::Salida, 'Dos', 'Texto');

        $this->postJson("/api/v1/mensajes/{$uno->id}/leer")->assertOk();
        $this->getJson('/api/v1/mensajes')->assertJsonPath('no_leidos', 1);

        $this->postJson('/api/v1/mensajes/leer-todos')->assertOk();
        $this->getJson('/api/v1/mensajes')->assertJsonPath('no_leidos', 0);
    }

    public function test_no_puede_tocar_mensajes_de_otra_cuenta(): void
    {
        $ajeno = app(Mensajero::class)->enviar(Apoderado::factory()->create(), TipoMensaje::Entrada, 'Ajeno', 'Texto');

        $this->postJson("/api/v1/mensajes/{$ajeno->id}/leer")->assertNotFound();
        $this->assertNull($ajeno->fresh()->leido_en);
        $this->getJson('/api/v1/mensajes')->assertJsonCount(0, 'mensajes');
    }

    public function test_registra_su_telefono_para_push_y_lo_hereda_la_ultima_cuenta(): void
    {
        $this->postJson('/api/v1/dispositivos', ['token' => 'tok-1', 'plataforma' => 'android'])->assertOk();
        $this->assertDatabaseHas('dispositivos', ['token' => 'tok-1', 'user_id' => $this->apoderado->user_id]);

        $otra = User::factory()->rol(Rol::Apoderado)->create();
        Sanctum::actingAs($otra);
        $this->postJson('/api/v1/dispositivos', ['token' => 'tok-1', 'plataforma' => 'ios'])->assertOk();

        $this->assertDatabaseCount('dispositivos', 1);
        $this->assertDatabaseHas('dispositivos', ['token' => 'tok-1', 'user_id' => $otra->id, 'plataforma' => 'ios']);
    }
}
