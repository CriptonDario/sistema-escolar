<?php

namespace Tests\Feature;

use App\Enums\MetodoPago;
use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\ConceptoPago;
use App\Models\Grado;
use App\Models\Matricula;
use App\Models\Pago;
use App\Models\Seccion;
use App\Models\SolicitudMatricula;
use App\Models\User;
use App\Services\Matriculas\MatriculaEnLinea;
use App\Support\Integraciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatriculaEnLineaTest extends TestCase
{
    use RefreshDatabase;

    private Apoderado $maria;

    private Alumno $ana;

    private Grado $primero;

    private Grado $segundo;

    private ConceptoPago $concepto;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        $this->travelTo('2026-12-01 10:00:00');

        $this->primero = Grado::factory()->create(['nivel' => 'secundaria', 'nombre' => '1ro Secundaria', 'orden' => 1]);
        $this->segundo = Grado::factory()->create(['nivel' => 'secundaria', 'nombre' => '2do Secundaria', 'orden' => 2]);
        $aula = Seccion::factory()->for($this->primero)->create(['nombre' => 'A']);
        Seccion::factory()->for($this->segundo)->create(['nombre' => 'A']);
        Seccion::factory()->for($this->segundo)->create(['nombre' => 'B']);

        $this->ana = Alumno::factory()->for($aula)->create(['nombres' => 'Ana', 'apellidos' => 'Pérez']);
        $this->maria = Apoderado::factory()->create(['nombres' => 'María Pérez']);
        $this->maria->hijos()->attach($this->ana, ['parentesco' => 'madre']);

        $this->concepto = ConceptoPago::query()->create(['nombre' => 'Matrícula', 'tipo' => 'matricula', 'monto' => 300]);
    }

    private function abrir(int $anio = 2027, ?string $hasta = null): void
    {
        app(MatriculaEnLinea::class)->configurar(true, $anio, $hasta);
    }

    private function activarPagoEnLinea(): void
    {
        $config = app(Integraciones::class);
        $config->guardar('mp_activo', '1');
        $config->guardar('mp_token', 'TEST-token-del-colegio');
    }

    private function solicitar(): SolicitudMatricula
    {
        $this->actingAs($this->maria->user)
            ->post("/familia/matricula/hijos/{$this->ana->id}", ['grado_id' => $this->segundo->id])
            ->assertSessionHasNoErrors();

        return SolicitudMatricula::query()->where('alumno_id', $this->ana->id)->firstOrFail();
    }

    /** Mercado Pago responde que el pago existe y con qué referencia. */
    private function pagoEnMercadoPago(string $id, string $referencia, string $estado = 'approved', float $monto = 300): void
    {
        Http::fake(["api.mercadopago.com/v1/payments/{$id}" => Http::response([
            'id' => (int) $id, 'status' => $estado, 'external_reference' => $referencia, 'transaction_amount' => $monto,
        ])]);
    }

    // ── El administrador la activa ───────────────────────────────────────────

    public function test_esta_cerrada_hasta_que_el_administrador_la_abre(): void
    {
        $this->actingAs($this->maria->user);

        $this->get('/familia/matricula')->assertOk()->assertSee('no está abierta')->assertDontSee('Solicitar matrícula');
        $this->post("/familia/matricula/hijos/{$this->ana->id}", ['grado_id' => $this->segundo->id])->assertSessionHasErrors('matricula');
        $this->assertDatabaseCount('solicitudes_matricula', 0);
    }

    public function test_el_administrador_abre_y_cierra_la_matricula_en_linea(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/matriculas')->assertOk()->assertSee('Matrícula en línea')->assertSee('Cerrada');

        $this->put('/matriculas/en-linea', ['matricula_abierta' => '1', 'matricula_anio' => 2027, 'matricula_hasta' => '2027-02-28'])
            ->assertSessionHasNoErrors()->assertSessionHas('ok');
        $this->assertTrue(app(MatriculaEnLinea::class)->abierta());
        $this->get('/matriculas')->assertOk()->assertSee('Abierta para 2027')->assertSee('S/ 300.00');

        $this->put('/matriculas/en-linea', ['matricula_anio' => 2027])->assertSessionHasNoErrors();
        $this->assertFalse(app(MatriculaEnLinea::class)->abierta());
    }

    public function test_no_se_puede_abrir_sin_un_concepto_de_matricula_ni_para_un_anio_pasado(): void
    {
        $this->concepto->update(['activo' => false]);
        $this->actingAs(User::factory()->create());

        $this->put('/matriculas/en-linea', ['matricula_abierta' => '1', 'matricula_anio' => 2027])->assertSessionHasErrors('matricula_abierta');
        $this->put('/matriculas/en-linea', ['matricula_abierta' => '1', 'matricula_anio' => 2025])->assertSessionHasErrors('matricula_anio');
        $this->assertFalse(app(MatriculaEnLinea::class)->activada());
    }

    public function test_solo_el_administrador_la_configura(): void
    {
        $this->actingAs(User::factory()->rol(Rol::Guardia)->create())
            ->put('/matriculas/en-linea', ['matricula_abierta' => '1', 'matricula_anio' => 2027])->assertForbidden();
    }

    public function test_pasada_la_fecha_limite_se_cierra_sola(): void
    {
        $this->abrir(2027, '2026-11-30');
        $this->actingAs($this->maria->user);

        $this->get('/familia/matricula')->assertOk()->assertSee('no está abierta');
        $this->post("/familia/matricula/hijos/{$this->ana->id}", ['grado_id' => $this->segundo->id])->assertSessionHasErrors('matricula');
    }

    // ── El apoderado solicita: sin pago no hay matrícula ─────────────────────

    public function test_al_solicitar_queda_pendiente_de_pago_y_todavia_no_esta_matriculado(): void
    {
        $this->abrir();
        $this->actingAs($this->maria->user);

        // El portal propone el grado siguiente al actual.
        $this->get('/familia/matricula')->assertOk()->assertSee('está abierta')->assertSee('S/ 300.00')
            ->assertSee('<option value="'.$this->segundo->id.'" selected>', false);

        $solicitud = $this->solicitar();

        $this->assertTrue($solicitud->pendiente());
        $this->assertSame(300.0, $solicitud->monto);
        $this->assertDatabaseCount('matriculas', 0);
        $this->get('/familia/matricula')->assertOk()
            ->assertSee('Solicitud registrada para 2do Secundaria')->assertSee('Falta el pago')
            ->assertSee('Paga en secretaría')->assertDontSee('Pagar en línea ahora');
    }

    public function test_un_apoderado_no_matricula_ni_paga_por_hijos_ajenos(): void
    {
        $this->abrir();
        $solicitud = $this->solicitar();
        $otro = Apoderado::factory()->create();

        $this->actingAs($otro->user);
        $this->post("/familia/matricula/hijos/{$this->ana->id}", ['grado_id' => $this->segundo->id])->assertForbidden();
        $this->post("/familia/matricula/solicitudes/{$solicitud->id}/pagar")->assertForbidden();
        $this->get('/familia/matricula')->assertOk()->assertDontSee('Ana Pérez');
    }

    public function test_no_se_puede_pedir_un_grado_inexistente_ni_matricular_dos_veces(): void
    {
        $this->abrir();
        $this->actingAs($this->maria->user);

        $this->post("/familia/matricula/hijos/{$this->ana->id}", ['grado_id' => 9999])->assertSessionHasErrors('matricula');

        Matricula::query()->create(['alumno_id' => $this->ana->id, 'seccion_id' => $this->segundo->secciones()->first()->id, 'anio' => 2027, 'fecha' => '2026-12-01']);
        $this->post("/familia/matricula/hijos/{$this->ana->id}", ['grado_id' => $this->segundo->id])->assertSessionHasErrors('matricula');
        $this->get('/familia/matricula')->assertOk()->assertSee('Matriculado en 2do Secundaria A');
    }

    // ── Pago en secretaría ───────────────────────────────────────────────────

    public function test_cuando_secretaria_registra_el_pago_la_matricula_se_confirma_sola(): void
    {
        $this->abrir();
        $solicitud = $this->solicitar();

        $this->actingAs(User::factory()->create())->post('/pagos', [
            'alumno_id' => $this->ana->id, 'concepto_id' => $this->concepto->id, 'anio' => 2027,
            'monto' => 300, 'fecha' => '2026-12-01', 'metodo' => 'efectivo',
        ])->assertSessionHasNoErrors();

        $this->assertSame(SolicitudMatricula::MATRICULADO, $solicitud->refresh()->estado);
        $matricula = Matricula::query()->where(['alumno_id' => $this->ana->id, 'anio' => 2027])->firstOrFail();
        $this->assertSame($this->segundo->id, $matricula->seccion->grado_id);
        // Es la matrícula del año que viene: su aula de este año no cambia.
        $this->assertSame($this->primero->id, $this->ana->refresh()->seccion->grado_id);
        $this->assertDatabaseHas('mensajes', ['apoderado_id' => $this->maria->id, 'titulo' => 'Matrícula confirmada']);
    }

    public function test_si_ya_habia_pagado_en_secretaria_queda_matriculado_al_solicitar(): void
    {
        $this->abrir();
        Pago::query()->create([
            'alumno_id' => $this->ana->id, 'concepto_id' => $this->concepto->id, 'anio' => 2027,
            'monto' => 300, 'fecha' => '2026-11-20', 'metodo' => 'yape',
        ]);

        $solicitud = $this->solicitar();

        $this->assertSame(SolicitudMatricula::MATRICULADO, $solicitud->estado);
        $this->assertDatabaseHas('matriculas', ['alumno_id' => $this->ana->id, 'anio' => 2027]);
    }

    public function test_un_pago_de_pension_o_de_otro_anio_no_matricula(): void
    {
        $this->abrir();
        $solicitud = $this->solicitar();
        $pension = ConceptoPago::query()->create(['nombre' => 'Pensión', 'tipo' => 'pension', 'monto' => 350]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->post('/pagos', ['alumno_id' => $this->ana->id, 'concepto_id' => $pension->id, 'anio' => 2027, 'mes' => 3, 'monto' => 350, 'fecha' => '2026-12-01', 'metodo' => 'efectivo'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/pagos', ['alumno_id' => $this->ana->id, 'concepto_id' => $this->concepto->id, 'anio' => 2026, 'monto' => 300, 'fecha' => '2026-12-01', 'metodo' => 'efectivo'])->assertSessionHasNoErrors();

        $this->assertTrue($solicitud->refresh()->pendiente());
        $this->assertDatabaseCount('matriculas', 0);
    }

    // ── Pago en línea ────────────────────────────────────────────────────────

    public function test_paga_en_linea_y_queda_matriculado_al_instante(): void
    {
        $this->abrir();
        $this->activarPagoEnLinea();
        $solicitud = $this->solicitar();

        Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'pref-1', 'init_point' => 'https://www.mercadopago.com.pe/checkout/v1/redirect?pref_id=pref-1'], 201)]);

        $this->get('/familia/matricula')->assertOk()->assertSee('Pagar en línea ahora');
        $this->post("/familia/matricula/solicitudes/{$solicitud->id}/pagar")
            ->assertRedirect('https://www.mercadopago.com.pe/checkout/v1/redirect?pref_id=pref-1');

        Http::assertSent(fn ($peticion) => $peticion->hasHeader('Authorization', 'Bearer TEST-token-del-colegio')
            && $peticion['external_reference'] === $solicitud->referencia
            && $peticion['items'][0]['unit_price'] == 300
            && $peticion['items'][0]['currency_id'] === 'PEN');

        // Mercado Pago lo devuelve al sistema con el número de pago.
        $this->pagoEnMercadoPago('123456789', $solicitud->referencia);
        $this->app['auth']->forgetGuards();
        $this->get('/matricula/retorno?payment_id=123456789&status=approved')->assertOk()->assertSee('Pago recibido');

        $this->assertSame(SolicitudMatricula::MATRICULADO, $solicitud->refresh()->estado);
        $this->assertDatabaseHas('matriculas', ['alumno_id' => $this->ana->id, 'anio' => 2027]);
        $pago = Pago::query()->sole();
        $this->assertSame(MetodoPago::EnLinea, $pago->metodo);
        $this->assertSame('123456789', $pago->numero_operacion);
        $this->assertDatabaseHas('mensajes', ['apoderado_id' => $this->maria->id, 'titulo' => 'Matrícula confirmada']);
    }

    public function test_el_aviso_repetido_de_la_pasarela_no_cobra_ni_matricula_dos_veces(): void
    {
        $this->abrir();
        $this->activarPagoEnLinea();
        $solicitud = $this->solicitar();
        $this->pagoEnMercadoPago('555', $solicitud->referencia);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/pagos/mercadopago', ['type' => 'payment', 'data' => ['id' => '555']])->assertOk();
        $this->postJson('/api/v1/pagos/mercadopago?topic=payment&id=555')->assertOk();
        $this->get('/matricula/retorno?payment_id=555')->assertOk()->assertSee('Pago recibido');

        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseCount('matriculas', 1);
        $this->assertSame(1, $this->maria->mensajes()->where('titulo', 'Matrícula confirmada')->count());
    }

    public function test_no_basta_con_decir_que_se_pago_el_pago_se_comprueba_con_la_pasarela(): void
    {
        $this->abrir();
        $this->activarPagoEnLinea();
        $solicitud = $this->solicitar();
        $this->app['auth']->forgetGuards();

        // Rechazado.
        $this->pagoEnMercadoPago('700', $solicitud->referencia, 'rejected');
        $this->get('/matricula/retorno?payment_id=700&status=approved')->assertOk()->assertSee('Todavía no vemos el pago');

        // Aprobado, pero por menos de lo que cuesta.
        $this->pagoEnMercadoPago('701', $solicitud->referencia, 'approved', 1);
        $this->get('/matricula/retorno?payment_id=701')->assertOk()->assertSee('Todavía no vemos el pago');

        // Un pago que Mercado Pago no conoce, o un id que no es un número.
        Http::fake(['api.mercadopago.com/v1/payments/999' => Http::response(['message' => 'not found'], 404)]);
        $this->get('/matricula/retorno?payment_id=999')->assertOk()->assertSee('Todavía no vemos el pago');
        $this->get('/matricula/retorno?payment_id=../../users')->assertOk()->assertSee('Todavía no vemos el pago');
        $this->get('/matricula/retorno')->assertOk()->assertSee('Todavía no vemos el pago');

        $this->assertTrue($solicitud->refresh()->pendiente());
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseCount('matriculas', 0);
    }

    public function test_sin_pasarela_configurada_no_se_ofrece_pagar_en_linea(): void
    {
        $this->abrir();
        $solicitud = $this->solicitar();

        $this->post("/familia/matricula/solicitudes/{$solicitud->id}/pagar")->assertSessionHasErrors('matricula');
        Http::assertNothingSent();
    }

    public function test_los_alumnos_se_reparten_entre_las_secciones_del_grado(): void
    {
        $this->abrir();
        $otro = Alumno::factory()->for($this->ana->seccion)->create();
        $this->maria->hijos()->attach($otro, ['parentesco' => 'madre']);

        foreach ([$this->ana, $otro] as $alumno) {
            Pago::query()->create(['alumno_id' => $alumno->id, 'concepto_id' => $this->concepto->id, 'anio' => 2027, 'monto' => 300, 'fecha' => '2026-11-20', 'metodo' => 'efectivo']);
            $this->actingAs($this->maria->user)->post("/familia/matricula/hijos/{$alumno->id}", ['grado_id' => $this->segundo->id])->assertSessionHasNoErrors();
        }

        $aulas = Matricula::query()->where('anio', 2027)->with('seccion')->get()->map(fn ($m) => $m->seccion->nombre)->sort()->values()->all();
        $this->assertSame(['A', 'B'], $aulas);
    }

    // ── Desde la app ─────────────────────────────────────────────────────────

    public function test_la_app_consulta_solicita_y_recibe_la_direccion_para_pagar(): void
    {
        $this->abrir();
        $this->activarPagoEnLinea();
        Sanctum::actingAs($this->maria->user);

        $this->getJson('/api/v1/matricula')->assertOk()
            ->assertJsonPath('abierta', true)->assertJsonPath('anio', 2027)->assertJsonPath('monto', 300)
            ->assertJsonPath('pago_en_linea', true)
            ->assertJsonPath('hijos.0.nombre', 'Ana Pérez')->assertJsonPath('hijos.0.estado', 'sin_solicitar')
            ->assertJsonPath('hijos.0.grado_sugerido_id', $this->segundo->id)
            ->assertJsonCount(2, 'grados');

        $respuesta = $this->postJson("/api/v1/matricula/hijos/{$this->ana->id}", ['grado_id' => $this->segundo->id])->assertOk()
            ->assertJsonPath('hijos.0.estado', 'pendiente_pago')->assertJsonPath('hijos.0.grado_solicitado', '2do Secundaria');

        Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'pref-9', 'init_point' => 'https://www.mercadopago.com.pe/pagar/pref-9'], 201)]);
        $this->postJson('/api/v1/matricula/solicitudes/'.$respuesta->json('hijos.0.solicitud_id').'/pagar')->assertOk()
            ->assertJsonPath('url', 'https://www.mercadopago.com.pe/pagar/pref-9');

        // Cerrada: la app recibe el motivo.
        app(MatriculaEnLinea::class)->configurar(false, 2027, null);
        $otro = Alumno::factory()->create();
        $this->maria->hijos()->attach($otro, ['parentesco' => 'madre']);
        $this->postJson("/api/v1/matricula/hijos/{$otro->id}", ['grado_id' => $this->segundo->id])->assertUnprocessable()->assertJsonValidationErrors('matricula');
    }

    public function test_la_matricula_en_linea_de_la_api_es_solo_para_apoderados(): void
    {
        Sanctum::actingAs(User::factory()->rol(Rol::Alumno)->create());
        $this->getJson('/api/v1/matricula')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/matricula')->assertUnauthorized();
    }

    public function test_el_token_de_la_pasarela_se_guarda_cifrado_desde_integraciones(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/integraciones')->assertOk()->assertSee('Pagos en línea (Mercado Pago)');

        $this->put('/integraciones', [
            'wa_proveedor' => 'ultramsg', 'sunat_serie_boleta' => 'B001', 'sunat_serie_factura' => 'F001', 'sunat_afectacion' => 'exonerado',
            'mp_activo' => '1', 'mp_token' => 'APP_USR-secreto',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(app(MatriculaEnLinea::class)->pagoEnLineaDisponible());
        $this->assertDatabaseMissing('ajustes', ['valor' => 'APP_USR-secreto']);
        $this->get('/integraciones')->assertOk()->assertDontSee('APP_USR-secreto');
    }
}
