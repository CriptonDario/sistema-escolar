<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Grado;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * El panel y la API tienen pantallas y recursos con el mismo nombre
 * (alumnos, personal, grados...). Estas pruebas vigilan que no se pisen.
 */
class RutasDelPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_ningun_nombre_de_ruta_se_repite_entre_el_panel_y_la_api(): void
    {
        foreach (Route::getRoutes() as $ruta) {
            $nombre = $ruta->getName();
            if ($nombre === null) {
                continue;
            }

            $esDeLaApi = str_starts_with($ruta->uri(), 'api/');

            $this->assertSame(
                $esDeLaApi,
                str_starts_with($nombre, 'api.'),
                "La ruta [{$nombre}] ({$ruta->uri()}) debe llamarse 'api.…' solo si es de la API."
            );
        }
    }

    public function test_los_nombres_del_panel_llevan_a_pantallas_del_panel(): void
    {
        foreach (['alumnos.index', 'personal.index', 'grados.index', 'apoderados.index', 'permisos.index',
            'alumnos.store', 'personal.store', 'grados.store', 'apoderados.store', 'permisos.store'] as $nombre) {
            $this->assertStringStartsNotWith('/api/', route($nombre, [], false), $nombre);
        }
    }

    public function test_cada_enlace_del_menu_abre_su_pantalla(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/panel')->assertOk()->getContent();
        preg_match_all('/<a href="([^"#]+)"/', $html, $coincidencias);
        $enlaces = array_unique(array_filter($coincidencias[1], fn ($url) => ! str_contains($url, '/kiosco')));

        $this->assertGreaterThan(20, count($enlaces));

        foreach ($enlaces as $url) {
            $this->assertStringNotContainsString('/api/', $url);
            $this->get($url)->assertOk();
        }
    }

    /**
     * Los formularios del panel se envían a la dirección que escribe la vista,
     * no a una escrita a mano en la prueba: así se detecta si apuntan mal.
     */
    public function test_los_formularios_del_panel_guardan_de_verdad(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());

        $accion = function (string $pantalla, string $fragmento): string {
            $html = $this->get($pantalla)->assertOk()->getContent();
            preg_match_all('/<form action="([^"]+)" method="POST"/', $html, $c);
            $url = collect($c[1])->first(fn ($u) => str_ends_with(parse_url($u, PHP_URL_PATH), $fragmento));
            $this->assertNotNull($url, "No hay formulario hacia {$fragmento} en {$pantalla}");

            return $url;
        };

        $this->post($accion('/grados', '/grados'), ['nivel' => 'primaria', 'nombre' => '1ro Primaria', 'orden' => 1, 'secciones' => ['A']])
            ->assertSessionHasNoErrors()->assertSessionHas('ok');
        $seccion = Grado::query()->firstOrFail()->secciones()->firstOrFail();

        $this->post($accion('/alumnos/create', '/alumnos'), ['codigo' => '1PA01', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'seccion_id' => $seccion->id])
            ->assertSessionHasNoErrors()->assertRedirect(url('/alumnos'));
        $alumno = Alumno::query()->where('codigo', '1PA01')->firstOrFail();

        $this->post($accion("/alumnos/{$alumno->id}/edit", "/alumnos/{$alumno->id}"), ['_method' => 'PUT', 'codigo' => '1PA01', 'nombres' => 'Ana María', 'apellidos' => 'Pérez', 'activo' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Ana María', $alumno->fresh()->nombres);

        $this->post($accion('/personal/create', '/personal'), ['codigo' => 'DOC01', 'nombres' => 'Luis', 'apellidos' => 'Ramos', 'tipo' => 'docente'])
            ->assertSessionHasNoErrors()->assertRedirect(url('/personal'));
        $this->assertSame(1, Personal::query()->count());

        $this->post($accion('/apoderados', '/apoderados'), ['nombres' => 'María Pérez', 'telefono' => '987654321', 'hijos' => [['alumno_id' => $alumno->id]]])
            ->assertSessionHasNoErrors()->assertSessionHas('ok');
        $this->assertSame(1, Apoderado::query()->count());

        $this->post($accion('/permisos', '/permisos'), ['alumno_id' => $alumno->id, 'tipo' => 'inasistencia', 'fecha' => today()->toDateString(), 'motivo' => 'Viaje'])
            ->assertSessionHasNoErrors()->assertSessionHas('ok');
        $this->assertDatabaseCount('permisos', 1);
    }

    public function test_el_guardia_tambien_llega_a_sus_pantallas_desde_el_menu(): void
    {
        $this->actingAs(User::factory()->rol(Rol::Guardia)->create());

        $html = $this->get('/panel')->assertOk()->getContent();
        preg_match_all('/<a href="([^"#]+)"/', $html, $coincidencias);

        foreach (array_unique($coincidencias[1]) as $url) {
            $this->assertStringNotContainsString('/api/', $url);
            $this->get($url)->assertOk();
        }
    }
}
