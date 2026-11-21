<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\User;
use App\Models\Visita;
use App\Services\Visitas\PasesDeVisita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Credenciales genéricas y numeradas para los visitantes. */
class PasesDeVisitaTest extends TestCase
{
    use RefreshDatabase;

    private User $guardia;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->guardia = User::factory()->rol(Rol::Guardia)->create();
    }

    private function ingresar(string $nombre, ?string $pase): void
    {
        $this->actingAs($this->guardia)
            ->post('/visitas', ['nombre' => $nombre, 'motivo' => 'Reunión', 'pase' => $pase])
            ->assertSessionHasNoErrors();
    }

    public function test_el_colegio_tiene_diez_pases_numerados_y_el_administrador_cambia_la_cantidad(): void
    {
        $pases = app(PasesDeVisita::class);
        $this->assertSame('VIS-01', $pases->codigos()[0]);
        $this->assertCount(10, $pases->codigos());

        $this->actingAs($this->guardia)->put('/visitas/pases', ['cantidad' => 20])->assertForbidden();

        $this->actingAs(User::factory()->create())->put('/visitas/pases', ['cantidad' => 25])->assertSessionHas('ok');
        $this->assertCount(25, app(PasesDeVisita::class)->codigos());
        $this->actingAs(User::factory()->create())->put('/visitas/pases', ['cantidad' => 500])->assertSessionHasErrors('cantidad');
    }

    public function test_se_imprimen_como_credencial_generica_sin_nombre_de_persona(): void
    {
        $this->actingAs($this->guardia)->get('/visitas/pases')->assertOk()
            ->assertSee('PASE DE VISITANTE')
            ->assertSee('Código QR del carné VIS-01')
            ->assertSee('Código QR del carné VIS-10')
            ->assertDontSee('VIS-11')
            ->assertSee('Devuélvalo en la puerta.')
            ->assertDontSee('Apellidos y nombres')
            ->assertDontSee('personal e intransferible');
    }

    public function test_el_guardia_entrega_un_pase_al_registrar_el_ingreso(): void
    {
        $this->actingAs($this->guardia)->get('/visitas')->assertOk()->assertSee('10 de 10 pases libres');

        $this->ingresar('Carlos Ruiz', 'vis-03');

        $this->assertDatabaseHas('visitas', ['nombre' => 'Carlos Ruiz', 'pase' => 'VIS-03', 'salida' => null]);
        $this->get('/visitas')->assertOk()->assertSee('9 de 10 pases libres')->assertDontSee('<option value="VIS-03"', false);
    }

    public function test_un_pase_en_uso_o_inexistente_no_se_puede_entregar_y_el_pase_es_opcional(): void
    {
        $this->ingresar('Carlos Ruiz', 'VIS-03');

        $this->post('/visitas', ['nombre' => 'Otra Persona', 'motivo' => 'Entrega', 'pase' => 'VIS-03'])->assertSessionHasErrors('pase');
        $this->post('/visitas', ['nombre' => 'Otra Persona', 'motivo' => 'Entrega', 'pase' => 'VIS-99'])->assertSessionHasErrors('pase');
        $this->assertDatabaseCount('visitas', 1);

        $this->ingresar('Sin Pase', null);
        $this->assertDatabaseHas('visitas', ['nombre' => 'Sin Pase', 'pase' => null]);
    }

    public function test_al_devolver_el_pase_se_marca_la_salida_y_el_pase_vuelve_a_estar_libre(): void
    {
        $this->ingresar('Carlos Ruiz', 'VIS-03');

        $this->post('/visitas/pases/devolver', ['codigo' => ' vis-03 '])->assertSessionHas('ok');

        $this->assertNotNull(Visita::query()->sole()->salida);
        $this->assertContains('VIS-03', app(PasesDeVisita::class)->libres());

        // Ya no lo tiene nadie.
        $this->post('/visitas/pases/devolver', ['codigo' => 'VIS-03'])->assertSessionHas('error');

        // Y se le puede entregar a otro visitante.
        $this->ingresar('Lucía Ramos', 'VIS-03');
        $this->assertSame(2, Visita::query()->where('pase', 'VIS-03')->count());
    }

    public function test_escanear_el_pase_en_el_kiosco_marca_la_salida_del_visitante(): void
    {
        $this->ingresar('Carlos Ruiz', 'VIS-03');

        $this->postJson('/kiosco/marcar', ['codigo' => 'VIS-03'])->assertOk()
            ->assertJsonPath('evento', 'salida')->assertJsonPath('registrado', true)
            ->assertJsonPath('mensaje', 'Salida de Carlos Ruiz (visitante)')
            ->assertJsonPath('detalle', 'Visitante · pase VIS-03');

        $this->assertNotNull(Visita::query()->sole()->salida);
        $this->assertDatabaseCount('asistencias', 0);

        $this->postJson('/kiosco/marcar', ['codigo' => 'VIS-03'])->assertStatus(422)
            ->assertJsonPath('message', 'El pase VIS-03 no está entregado a ningún visitante.');
    }

    public function test_escanear_el_pase_en_la_app_tambien_marca_la_salida(): void
    {
        $this->ingresar('Carlos Ruiz', 'VIS-07');
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->guardia);

        $this->postJson('/api/v1/asistencia/marcar', ['codigo' => 'vis-07'])->assertOk()
            ->assertJsonPath('evento', 'salida')
            ->assertJsonPath('persona.tipo', 'visitante')->assertJsonPath('persona.nombre', 'Carlos Ruiz');

        $this->assertNotNull(Visita::query()->sole()->salida);
    }

    public function test_el_carne_de_un_alumno_sigue_marcando_asistencia_como_siempre(): void
    {
        $alumno = Alumno::factory()->create(['codigo' => 'VISTA1']);

        $this->actingAs($this->guardia)->postJson('/kiosco/marcar', ['codigo' => 'VISTA1'])->assertOk()
            ->assertJsonPath('nombre', $alumno->nombreCompleto());
        $this->assertDatabaseCount('asistencias', 1);
    }
}
