<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\ConceptoPago;
use App\Models\Gasto;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\User;
use App\Models\Visita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdministracionFinanzasTest extends TestCase
{
    use RefreshDatabase;

    private Alumno $ana;

    private Apoderado $apoderado;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo('2026-05-15 10:00:00');

        $this->ana = Alumno::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez']);
        $this->apoderado = Apoderado::factory()->create();
        $this->apoderado->hijos()->attach($this->ana);

        $this->actingAs(User::factory()->create());
    }

    private function pagar(ConceptoPago $concepto, array $extra = [])
    {
        return $this->post('/pagos', $extra + [
            'alumno_id' => $this->ana->id, 'concepto_id' => $concepto->id, 'anio' => 2026,
            'monto' => 350, 'fecha' => '2026-05-15', 'metodo' => 'yape',
        ]);
    }

    public function test_conceptos_de_pago(): void
    {
        $this->post('/conceptos', ['nombre' => 'Pensión', 'tipo' => 'pension', 'monto' => 350])->assertSessionHas('ok');
        $this->post('/conceptos', ['nombre' => 'Pensión', 'tipo' => 'pension', 'monto' => 350])->assertSessionHasErrors('nombre');

        $concepto = ConceptoPago::query()->firstOrFail();
        $this->put("/conceptos/{$concepto->id}", ['nombre' => 'Pensión mensual', 'tipo' => 'pension', 'monto' => 380])->assertSessionHas('ok');
        $this->assertSame(380.0, $concepto->fresh()->monto);
        $this->get('/conceptos')->assertOk()->assertSee('Pensión mensual');
    }

    public function test_registra_un_pago_muestra_el_recibo_y_avisa_al_apoderado(): void
    {
        $pension = ConceptoPago::query()->create(['nombre' => 'Pensión', 'tipo' => 'pension', 'monto' => 350]);

        $respuesta = $this->pagar($pension, ['mes' => 3, 'numero_operacion' => 'YP-001']);
        $pago = Pago::query()->firstOrFail();
        $respuesta->assertRedirect(route('pagos.recibo', $pago));

        $this->get("/pagos/{$pago->id}/recibo")->assertOk()
            ->assertSee('N.° 000001')->assertSee('Ana Pérez')->assertSee('Pensión — Marzo 2026')->assertSee('S/ 350.00');

        $this->assertDatabaseHas('mensajes', [
            'apoderado_id' => $this->apoderado->id, 'tipo' => 'pago', 'titulo' => 'Pago registrado',
            'cuerpo' => 'Se registró el pago de Pensión — Marzo 2026 de Ana Pérez por S/ 350.00. Recibo N.° 000001.',
        ]);
        $this->get('/pagos')->assertOk()->assertSee('S/ 350.00')->assertSee('Ana Pérez');
    }

    public function test_la_pension_exige_mes_y_no_se_cobra_dos_veces(): void
    {
        $pension = ConceptoPago::query()->create(['nombre' => 'Pensión', 'tipo' => 'pension', 'monto' => 350]);

        $this->pagar($pension)->assertSessionHasErrors('mes');
        $this->pagar($pension, ['mes' => 3]);
        $this->pagar($pension, ['mes' => 3])->assertSessionHasErrors('mes');
        $this->assertSame(1, Pago::query()->count());

        // Anulada, se puede volver a cobrar.
        $this->delete('/pagos/'.Pago::query()->value('id'))->assertSessionHas('ok');
        $this->pagar($pension, ['mes' => 3])->assertSessionHasNoErrors();
        $this->assertSame(2, Pago::query()->count());
    }

    public function test_un_pago_que_no_es_pension_no_guarda_mes(): void
    {
        $matricula = ConceptoPago::query()->create(['nombre' => 'Matrícula', 'tipo' => 'matricula', 'monto' => 300]);

        $this->pagar($matricula, ['mes' => 3, 'monto' => 300]);

        $this->assertNull(Pago::query()->firstOrFail()->mes);
        $this->pagar($matricula, ['monto' => 0])->assertSessionHasErrors('monto');
        $this->pagar($matricula, ['fecha' => '2026-05-16'])->assertSessionHasErrors('fecha'); // no a futuro
    }

    public function test_estado_de_cuenta_en_el_panel_y_en_la_app(): void
    {
        $pension = ConceptoPago::query()->create(['nombre' => 'Pensión', 'tipo' => 'pension', 'monto' => 350]);
        $this->pagar($pension, ['mes' => 3]);
        $this->pagar($pension, ['mes' => 5]);

        // Hoy es 15 de mayo: marzo y mayo pagadas, abril vencida, junio en adelante pendientes.
        $this->get("/alumnos/{$this->ana->id}/estado-de-cuenta")->assertOk()
            ->assertSee('1 pensión(es) vencida(s)')->assertSee('S/ 700.00');

        Sanctum::actingAs($this->apoderado->user);
        $this->getJson("/api/v1/hijos/{$this->ana->id}/pagos")->assertOk()
            ->assertJsonPath('total_pagado', 700)
            ->assertJsonPath('pensiones_vencidas', 1)
            ->assertJsonPath('pensiones.0', ['mes' => 3, 'nombre' => 'Marzo', 'estado' => 'pagada'])
            ->assertJsonPath('pensiones.1.estado', 'vencida')
            ->assertJsonPath('pensiones.2.estado', 'pagada')
            ->assertJsonPath('pensiones.3.estado', 'pendiente')
            ->assertJsonCount(10, 'pensiones')
            ->assertJsonCount(2, 'pagos');

        $this->getJson('/api/v1/hijos/'.Alumno::factory()->create()->id.'/pagos')->assertForbidden();
    }

    public function test_gastos_con_total_y_anulacion(): void
    {
        $this->post('/gastos', ['categoria' => 'Servicios', 'descripcion' => 'Luz de mayo', 'monto' => 420.5, 'fecha' => '2026-05-10'])->assertSessionHas('ok');
        $this->post('/gastos', ['categoria' => 'Materiales', 'descripcion' => 'Plumones', 'monto' => 80, 'fecha' => '2026-05-12'])->assertSessionHas('ok');

        $this->get('/gastos')->assertOk()->assertSee('S/ 500.50')->assertSee('Luz de mayo');

        $this->delete('/gastos/'.Gasto::query()->where('categoria', 'Materiales')->value('id'))->assertSessionHas('ok');
        $this->get('/gastos')->assertSee('Gastado en el periodo')->assertSee('S/ 420.50');
    }

    public function test_inventario_entradas_salidas_y_stock_insuficiente(): void
    {
        $this->post('/inventario', ['nombre' => 'Papel bond', 'unidad' => 'millar', 'stock_minimo' => 2])->assertSessionHas('ok');
        $producto = Producto::query()->firstOrFail();
        $this->assertTrue($producto->stockBajo());

        $this->post("/inventario/{$producto->id}/mover", ['tipo' => 'entrada', 'cantidad' => 10, 'motivo' => 'Compra'])->assertSessionHas('ok');
        $this->post("/inventario/{$producto->id}/mover", ['tipo' => 'salida', 'cantidad' => 4, 'motivo' => 'Secretaría'])->assertSessionHas('ok');
        $this->assertSame(6, $producto->fresh()->stock);

        $this->post("/inventario/{$producto->id}/mover", ['tipo' => 'salida', 'cantidad' => 7, 'motivo' => 'Exceso'])->assertSessionHas('error');
        $this->assertSame(6, $producto->fresh()->stock);
        $this->assertDatabaseCount('movimientos_inventario', 2);

        $this->get('/inventario')->assertOk()->assertSee('Papel bond')->assertSee('Secretaría');
    }

    public function test_el_guardia_registra_visitas_y_anota_en_la_bitacora(): void
    {
        $this->actingAs(User::factory()->rol(Rol::Guardia)->create(['usuario' => 'porteria']));

        $this->post('/visitas', ['nombre' => 'Carlos Ruiz', 'documento' => '12345678', 'motivo' => 'Entrega de documentos', 'visita_a' => 'Secretaría'])
            ->assertSessionHas('ok');
        $visita = Visita::query()->firstOrFail();
        $this->get('/visitas')->assertOk()->assertSee('Carlos Ruiz')->assertSee('1 visitante(s) dentro');

        $this->travel(20)->minutes();
        $this->post("/visitas/{$visita->id}/salida")->assertSessionHas('ok');
        $this->assertSame('10:20', $visita->fresh()->salida->format('H:i'));
        $this->get('/visitas')->assertSee('0 visitante(s) dentro');

        $this->post('/incidencias', ['titulo' => 'Portón trabado', 'descripcion' => 'No cierra bien.', 'severidad' => 'media'])->assertSessionHas('ok');
        $this->get('/incidencias')->assertOk()->assertSee('Portón trabado')->assertSee('porteria');

        // El guardia no ve las finanzas.
        foreach (['pagos', 'conceptos', 'gastos', 'inventario'] as $pantalla) {
            $this->get("/{$pantalla}")->assertForbidden();
        }
    }

    public function test_direccion_lee_la_bitacora_pero_no_escribe(): void
    {
        $this->actingAs(User::factory()->rol(Rol::Direccion)->create());

        $this->get('/incidencias')->assertOk()->assertDontSee('Nueva anotación');
        $this->post('/incidencias', ['titulo' => 'X', 'descripcion' => 'Y', 'severidad' => 'baja'])->assertForbidden();
        $this->get('/visitas')->assertForbidden();
    }
}
