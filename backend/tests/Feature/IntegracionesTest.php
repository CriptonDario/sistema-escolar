<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Comprobante;
use App\Models\ConceptoPago;
use App\Models\Grado;
use App\Models\Pago;
use App\Models\Seccion;
use App\Models\User;
use App\Support\Integraciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegracionesTest extends TestCase
{
    use RefreshDatabase;

    private Alumno $ana;

    private Apoderado $apoderado;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->travelTo('2026-03-10 07:20:00');

        $aula = Seccion::factory()->for(Grado::factory()->create(['nombre' => '2do Secundaria']))->create(['nombre' => 'B']);
        $this->ana = Alumno::factory()->for($aula)->create(['codigo' => '2SB01', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
        $this->apoderado = Apoderado::factory()->create(['nombres' => 'María Pérez', 'telefono' => '987654321', 'dni' => '12345678']);
        $this->apoderado->hijos()->attach($this->ana);

        $this->actingAs(User::factory()->create());
    }

    private function configurar(array $valores): void
    {
        $config = app(Integraciones::class);
        foreach ($valores as $clave => $valor) {
            $config->guardar($clave, $valor);
        }
    }

    private function pago(float $monto = 350): Pago
    {
        $concepto = ConceptoPago::query()->firstOrCreate(['nombre' => 'Pensión'], ['tipo' => 'pension', 'monto' => $monto]);

        return Pago::query()->create([
            'alumno_id' => $this->ana->id, 'concepto_id' => $concepto->id, 'anio' => 2026, 'mes' => 3,
            'monto' => $monto, 'fecha' => '2026-03-10', 'metodo' => 'yape',
        ]);
    }

    // ── Configuración ────────────────────────────────────────────────────────

    public function test_guarda_la_configuracion_y_cifra_los_tokens(): void
    {
        $this->get('/integraciones')->assertOk()->assertSee('WhatsApp')->assertSee('Sofía')->assertSee('Nubefact');

        $this->put('/integraciones', [
            'wa_activo' => '1', 'wa_proveedor' => 'ultramsg', 'wa_instancia' => 'instance123', 'wa_token' => 'secreto-wa',
            'sofia_url' => 'https://sofia.test/colegio', 'sofia_token' => 'secreto-sofia',
            'sunat_ruc' => '20123456789', 'sunat_razon_social' => 'Colegio SAC', 'sunat_ruta' => 'https://api.nubefact.com/api/v1/abc',
            'sunat_token' => 'secreto-sunat', 'sunat_serie_boleta' => 'B001', 'sunat_serie_factura' => 'F001', 'sunat_afectacion' => 'exonerado',
        ])->assertSessionHas('ok');

        $config = app(Integraciones::class);
        $this->assertTrue($config->activo('wa_activo'));
        $this->assertFalse($config->activo('sofia_activo'));
        $this->assertSame('secreto-wa', $config->texto('wa_token'));

        // En la base el token no está en claro, y la pantalla nunca lo muestra.
        $guardado = DB::table('ajustes')->where('clave', 'wa_token')->value('valor');
        $this->assertNotSame('secreto-wa', $guardado);
        $this->assertStringNotContainsString('secreto-wa', $guardado);
        $this->get('/integraciones')->assertOk()->assertDontSee('secreto-wa')->assertDontSee('secreto-sunat')->assertSee('instance123');
    }

    public function test_un_token_en_blanco_conserva_el_guardado(): void
    {
        $this->configurar(['wa_token' => 'secreto-wa']);

        $this->put('/integraciones', [
            'wa_proveedor' => 'meta', 'wa_instancia' => '555', 'wa_token' => '',
            'sunat_serie_boleta' => 'B001', 'sunat_serie_factura' => 'F001', 'sunat_afectacion' => 'exonerado',
        ])->assertSessionHas('ok');

        $this->assertSame('secreto-wa', app(Integraciones::class)->texto('wa_token'));
        $this->assertSame('meta', app(Integraciones::class)->texto('wa_proveedor'));
    }

    public function test_valida_direcciones_ruc_y_series(): void
    {
        $this->put('/integraciones', [
            'wa_proveedor' => 'telegram', 'sofia_url' => 'http://sin-https.test', 'sunat_ruc' => '123',
            'sunat_ruta' => 'no-es-url', 'sunat_serie_boleta' => 'X001', 'sunat_serie_factura' => 'F1', 'sunat_afectacion' => 'otro',
        ])->assertSessionHasErrors(['wa_proveedor', 'sofia_url', 'sunat_ruc', 'sunat_ruta', 'sunat_serie_boleta', 'sunat_serie_factura', 'sunat_afectacion']);
    }

    public function test_solo_el_administrador_configura_las_integraciones(): void
    {
        $this->actingAs(User::factory()->rol(Rol::Guardia)->create());

        $this->get('/integraciones')->assertForbidden();
        $this->put('/integraciones', [])->assertForbidden();
    }

    // ── WhatsApp y Sofía ─────────────────────────────────────────────────────

    public function test_sin_nada_activo_el_ingreso_no_llama_a_ningun_servicio_externo(): void
    {
        Http::fake();

        $this->postJson('/kiosco/marcar', ['codigo' => '2SB01'])->assertOk();

        Http::assertNothingSent();
        $this->assertDatabaseCount('mensajes', 1);
    }

    public function test_el_ingreso_sale_por_whatsapp_con_ultramsg(): void
    {
        Http::fake(['api.ultramsg.com/*' => Http::response(['sent' => 'true'])]);
        $this->configurar(['wa_activo' => '1', 'wa_proveedor' => 'ultramsg', 'wa_instancia' => 'instance123', 'wa_token' => 'secreto-wa']);

        $this->postJson('/kiosco/marcar', ['codigo' => '2SB01'])->assertOk();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.ultramsg.com/instance123/messages/chat'
            && $r['token'] === 'secreto-wa'
            && $r['to'] === '51987654321@c.us'
            && str_contains($r['body'], 'Ana Pérez ingresó al colegio a las 07:20'));
        Http::assertSentCount(1);
    }

    public function test_el_ingreso_sale_por_whatsapp_con_meta(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $this->configurar(['wa_activo' => '1', 'wa_proveedor' => 'meta', 'wa_instancia' => '555000', 'wa_token' => 'token-meta']);

        $this->postJson('/kiosco/marcar', ['codigo' => '2SB01'])->assertOk();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v21.0/555000/messages'
            && $r->hasHeader('Authorization', 'Bearer token-meta')
            && $r['to'] === '51987654321'
            && $r['type'] === 'text');
    }

    public function test_sofia_recibe_ingresos_y_salidas_pero_no_otros_mensajes(): void
    {
        Http::fake(['sofia.test/*' => Http::response(['ok' => true])]);
        $this->configurar(['sofia_activo' => '1', 'sofia_url' => 'https://sofia.test/colegio/asistencia', 'sofia_token' => 'secreto-sofia']);

        $this->postJson('/kiosco/marcar', ['codigo' => '2SB01'])->assertOk();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://sofia.test/colegio/asistencia'
            && $r->hasHeader('Authorization', 'Bearer secreto-sofia')
            && $r['evento'] === 'entrada'
            && $r['alumno'] === 'Ana Pérez'
            && $r['grado'] === '2do Secundaria B'
            && $r['apoderado'] === 'María Pérez'
            && $r['telefono'] === '51987654321'
            && $r['hora'] === '2026-03-10T07:20:00-05:00'
            && $r['referencia'] === '2SB01-20260310-entrada');

        // Un comunicado no es un evento de asistencia: a Sofía no le llega.
        $this->post('/comunicados', ['titulo' => 'Reunión', 'cuerpo' => 'Viernes', 'alcance' => 'todos']);
        Http::assertSentCount(1);
    }

    public function test_si_el_servicio_externo_falla_la_asistencia_queda_registrada(): void
    {
        Http::fake(['api.ultramsg.com/*' => Http::response('caído', 500)]);
        $this->configurar(['wa_activo' => '1', 'wa_proveedor' => 'ultramsg', 'wa_instancia' => 'i1', 'wa_token' => 't1']);

        $this->postJson('/kiosco/marcar', ['codigo' => '2SB01'])->assertOk()->assertJsonPath('evento', 'entrada');

        $this->assertDatabaseCount('asistencias', 1);
        $this->assertDatabaseCount('mensajes', 1);
    }

    public function test_mensaje_de_prueba_de_whatsapp(): void
    {
        $this->post('/integraciones/probar-whatsapp', ['telefono' => '987654321'])->assertSessionHas('error');

        Http::fake(['api.ultramsg.com/*' => Http::response(['sent' => 'true'])]);
        $this->configurar(['wa_activo' => '1', 'wa_proveedor' => 'ultramsg', 'wa_instancia' => 'i1', 'wa_token' => 't1']);

        $this->post('/integraciones/probar-whatsapp', ['telefono' => '987 654 321'])->assertSessionHas('ok');
        Http::assertSent(fn (Request $r) => $r['to'] === '51987654321@c.us');
    }

    // ── SUNAT ────────────────────────────────────────────────────────────────

    private function activarSunat(array $extra = []): void
    {
        $this->configurar($extra + [
            'sunat_activo' => '1', 'sunat_ruta' => 'https://api.nubefact.com/api/v1/abc', 'sunat_token' => 'token-nubefact',
        ]);
    }

    public function test_emite_una_boleta_exonerada_con_numeracion_correlativa(): void
    {
        Http::fake(['api.nubefact.com/*' => Http::response([
            'aceptada_por_sunat' => true, 'sunat_description' => 'La Boleta numero B001-1, ha sido aceptada',
            'enlace_del_pdf' => 'https://www.nubefact.com/cpe/abc.pdf',
        ])]);
        $this->activarSunat();
        $pago = $this->pago(350);

        $this->get("/pagos/{$pago->id}/recibo")->assertOk()->assertSee('Emitir comprobante electrónico');

        $this->post("/pagos/{$pago->id}/comprobante", [
            'tipo' => 'boleta', 'cliente_tipo_doc' => '1', 'cliente_numero_doc' => '12345678', 'cliente_nombre' => 'María Pérez',
        ])->assertSessionHas('ok');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.nubefact.com/api/v1/abc'
            && $r->hasHeader('Authorization', 'Token token="token-nubefact"')
            && $r['operacion'] === 'generar_comprobante'
            && $r['tipo_de_comprobante'] === 2
            && $r['serie'] === 'B001' && $r['numero'] === 1
            && $r['cliente_numero_de_documento'] === '12345678'
            && $r['fecha_de_emision'] === '10-03-2026'
            && $r['total'] == 350 && $r['total_exonerada'] == 350 && $r['total_igv'] == 0
            && $r['items'][0]['tipo_de_igv'] === 8
            && str_contains($r['items'][0]['descripcion'], 'Pensión — Marzo 2026 — Ana Pérez'));

        $comprobante = Comprobante::query()->firstOrFail();
        $this->assertSame('B001-00000001', $comprobante->numero());
        $this->assertSame(Comprobante::ACEPTADO, $comprobante->estado);
        $this->get("/pagos/{$pago->id}/recibo")->assertSee('B001-00000001')->assertSee('Ver PDF')->assertDontSee('Emitir comprobante electrónico');

        // El mismo pago no se factura dos veces; el siguiente pago toma el número 2.
        $this->post("/pagos/{$pago->id}/comprobante", ['tipo' => 'boleta', 'cliente_tipo_doc' => '-', 'cliente_nombre' => 'X'])->assertSessionHas('error');
        $otro = $this->pago(100);
        $otro->update(['mes' => 4]);
        $this->post("/pagos/{$otro->id}/comprobante", ['tipo' => 'boleta', 'cliente_tipo_doc' => '-', 'cliente_nombre' => 'Cliente varios']);
        $this->assertSame(2, Comprobante::query()->where('pago_id', $otro->id)->value('correlativo'));
    }

    public function test_la_factura_exige_ruc_y_el_modo_gravado_desglosa_el_igv(): void
    {
        Http::fake(['api.nubefact.com/*' => Http::response(['aceptada_por_sunat' => true, 'sunat_description' => 'Aceptada'])]);
        $this->activarSunat(['sunat_afectacion' => 'gravado', 'sunat_serie_factura' => 'F002']);
        $pago = $this->pago(118);

        $this->post("/pagos/{$pago->id}/comprobante", ['tipo' => 'factura', 'cliente_tipo_doc' => '1', 'cliente_numero_doc' => '12345678', 'cliente_nombre' => 'X'])
            ->assertSessionHasErrors('cliente_tipo_doc');
        $this->post("/pagos/{$pago->id}/comprobante", ['tipo' => 'factura', 'cliente_tipo_doc' => '6', 'cliente_numero_doc' => '123', 'cliente_nombre' => 'X'])
            ->assertSessionHasErrors('cliente_numero_doc');

        $this->post("/pagos/{$pago->id}/comprobante", ['tipo' => 'factura', 'cliente_tipo_doc' => '6', 'cliente_numero_doc' => '20123456789', 'cliente_nombre' => 'Empresa SAC'])
            ->assertSessionHas('ok');

        Http::assertSent(fn (Request $r) => $r['tipo_de_comprobante'] === 1 && $r['serie'] === 'F002'
            && $r['total_gravada'] == 100 && $r['total_igv'] == 18 && $r['total'] == 118
            && $r['items'][0]['tipo_de_igv'] === 1);
    }

    public function test_un_rechazo_de_nubefact_queda_registrado_y_permite_emitir_de_nuevo(): void
    {
        Http::fake(['api.nubefact.com/*' => Http::sequence()
            ->push(['errors' => 'El RUC del emisor no está habilitado', 'codigo' => 21], 400)
            ->push(['aceptada_por_sunat' => true, 'sunat_description' => 'Aceptada'])]);
        $this->activarSunat();
        $pago = $this->pago();
        $datos = ['tipo' => 'boleta', 'cliente_tipo_doc' => '-', 'cliente_nombre' => 'Cliente varios'];

        $this->post("/pagos/{$pago->id}/comprobante", $datos)->assertSessionHas('error');
        $this->assertDatabaseHas('comprobantes', ['correlativo' => 1, 'estado' => 'rechazado', 'mensaje_sunat' => 'El RUC del emisor no está habilitado']);

        // Rechazado: se puede emitir otro, que toma el siguiente número.
        $this->post("/pagos/{$pago->id}/comprobante", $datos)->assertSessionHas('ok');
        $this->assertDatabaseHas('comprobantes', ['correlativo' => 2, 'estado' => 'aceptado']);
    }

    public function test_si_nubefact_no_responde_el_comprobante_se_reenvia_con_el_mismo_numero(): void
    {
        Http::fake(['api.nubefact.com/*' => Http::sequence()
            ->pushFailedConnection()
            ->push(['aceptada_por_sunat' => true, 'sunat_description' => 'Aceptada'])]);
        $this->activarSunat();
        $pago = $this->pago();

        $this->post("/pagos/{$pago->id}/comprobante", ['tipo' => 'boleta', 'cliente_tipo_doc' => '-', 'cliente_nombre' => 'Cliente varios'])
            ->assertSessionHas('error');
        $comprobante = Comprobante::query()->firstOrFail();
        $this->assertSame(Comprobante::ERROR, $comprobante->estado);
        $this->get("/pagos/{$pago->id}/recibo")->assertSee('Reenviar a SUNAT');

        $this->post("/comprobantes/{$comprobante->id}/reenviar")->assertSessionHas('ok');

        $this->assertSame(Comprobante::ACEPTADO, $comprobante->fresh()->estado);
        $this->assertSame(1, Comprobante::query()->count());
        $this->post("/comprobantes/{$comprobante->id}/reenviar")->assertSessionHas('error'); // ya aceptado
    }

    public function test_sin_facturacion_configurada_no_se_ofrece_ni_se_emite(): void
    {
        Http::fake();
        $pago = $this->pago();

        $this->get("/pagos/{$pago->id}/recibo")->assertOk()->assertDontSee('Emitir comprobante electrónico');
        $this->post("/pagos/{$pago->id}/comprobante", ['tipo' => 'boleta', 'cliente_tipo_doc' => '-', 'cliente_nombre' => 'X'])->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertDatabaseCount('comprobantes', 0);
    }

    public function test_no_se_emite_comprobante_de_un_pago_anulado(): void
    {
        Http::fake();
        $this->activarSunat();
        $pago = $this->pago();
        $pago->update(['anulado_en' => now()]);

        $this->post("/pagos/{$pago->id}/comprobante", ['tipo' => 'boleta', 'cliente_tipo_doc' => '-', 'cliente_nombre' => 'X'])->assertSessionHas('error');
        Http::assertNothingSent();
    }
}
