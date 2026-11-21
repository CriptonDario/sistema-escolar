<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\TipoMensaje;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\ConceptoPago;
use App\Models\Grado;
use App\Models\Materia;
use App\Models\Nota;
use App\Models\Pago;
use App\Models\Seccion;
use App\Models\User;
use App\Services\Mensajeria\Mensajero;
use App\Support\CodigoQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PortalFamiliaTest extends TestCase
{
    use RefreshDatabase;

    private Apoderado $maria;

    private Alumno $ana;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo('2026-05-15 10:00:00');

        $aula = Seccion::factory()->for(Grado::factory()->create(['nivel' => 'secundaria', 'nombre' => '2do Secundaria']))->create(['nombre' => 'B']);
        $this->ana = Alumno::factory()->for($aula)->create(['nombres' => 'Ana', 'apellidos' => 'Pérez']);

        $this->maria = Apoderado::factory()->create([
            'nombres' => 'María Pérez', 'telefono' => '987654321',
            'user_id' => User::factory()->rol(Rol::Apoderado)->create(['usuario' => '987654321', 'password' => 'Familia2026']),
        ]);
        $this->maria->hijos()->attach($this->ana, ['parentesco' => 'madre']);
    }

    // ── Quién entra ──────────────────────────────────────────────────────────

    public function test_un_apoderado_activado_en_la_app_entra_a_su_portal(): void
    {
        $this->post('/login', ['usuario' => '+51 987 654 321', 'clave' => 'Familia2026'])->assertRedirect(route('familia.inicio'));

        $this->get('/familia')->assertOk()->assertSee('Hola, María')->assertSee('Ana Pérez')->assertSee('2do Secundaria B');
    }

    public function test_con_la_clave_inicial_no_entra_por_la_web_hasta_activarla_en_la_app(): void
    {
        $nuevo = Apoderado::factory()->create([
            'telefono' => '900000001',
            'user_id' => User::factory()->rol(Rol::Apoderado)->conClaveInicial()->create(['usuario' => '900000001']),
        ]);

        $this->post('/login', ['usuario' => '900000001', 'clave' => '123456'])->assertSessionHasErrors('usuario');
        $this->assertGuest();

        // En la app crea su contraseña...
        $token = $this->postJson('/api/v1/auth/login', ['usuario' => '900000001', 'clave' => '123456'])->json('token');
        $this->withToken($token)->postJson('/api/v1/auth/clave', ['clave_actual' => '123456', 'clave_nueva' => 'MiClave2026'])->assertOk();

        // ...y desde entonces la web lo deja pasar. (El navegador es otro
        // dispositivo: no lleva el token de la app.)
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->withoutToken();
        $this->post('/login', ['usuario' => '900000001', 'clave' => 'MiClave2026'])->assertRedirect(route('familia.inicio'));
        $this->assertAuthenticatedAs($nuevo->user);
    }

    public function test_alumnos_y_docentes_no_entran_por_la_web(): void
    {
        User::factory()->rol(Rol::Alumno)->create(['usuario' => 'ana@colegio.edu.pe', 'password' => 'Clave1234']);
        User::factory()->rol(Rol::Personal)->create(['usuario' => 'luis@colegio.edu.pe', 'password' => 'Clave1234']);

        $this->post('/login', ['usuario' => 'ana@colegio.edu.pe', 'clave' => 'Clave1234'])->assertSessionHasErrors('usuario');
        $this->post('/login', ['usuario' => 'luis@colegio.edu.pe', 'clave' => 'Clave1234'])->assertSessionHasErrors('usuario');
        $this->assertGuest();
    }

    public function test_el_apoderado_no_llega_al_panel_del_colegio(): void
    {
        $this->actingAs($this->maria->user);

        // Sigue con su sesión, pero lo llevan a su portal.
        $this->get('/panel')->assertRedirect(route('familia.inicio'));
        $this->get('/alumnos')->assertRedirect(route('familia.inicio'));
        $this->get('/pagos')->assertRedirect(route('familia.inicio'));
        $this->assertAuthenticated();
    }

    public function test_el_personal_del_colegio_no_entra_al_portal_de_padres(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/familia')->assertRedirect(route('login'));
    }

    public function test_una_cuenta_desactivada_sale_del_portal(): void
    {
        $this->actingAs($this->maria->user);
        $this->get('/familia')->assertOk();

        $this->maria->user->update(['activo' => false]);

        $this->get('/familia')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ── Qué ve ───────────────────────────────────────────────────────────────

    public function test_cada_apoderado_ve_solo_sus_mensajes_y_sus_hijos(): void
    {
        $otro = Apoderado::factory()->create();
        $ajeno = Alumno::factory()->create(['nombres' => 'Carlos', 'apellidos' => 'Ajeno']);
        $otro->hijos()->attach($ajeno);

        $mensajero = app(Mensajero::class);
        $mensajero->avisarApoderadosDe($this->ana, TipoMensaje::Entrada, 'Ingresó al colegio', 'Ana Pérez ingresó a las 07:20.');
        $mensajero->avisarApoderadosDe($ajeno, TipoMensaje::Entrada, 'Ingresó al colegio', 'Carlos Ajeno ingresó a las 07:25.');

        $this->actingAs($this->maria->user);

        $this->get('/familia')->assertOk()
            ->assertSee('Ana Pérez ingresó a las 07:20.')
            ->assertSee('1 sin leer')
            ->assertDontSee('Carlos Ajeno');

        // Ni cambiando el número en la dirección ve al alumno de otra familia.
        $this->get("/familia/hijos/{$ajeno->id}")->assertForbidden();
        foreach (['notas', 'pagos', 'asistencia'] as $ver) {
            $this->get("/familia/hijos/{$ajeno->id}?ver={$ver}")->assertForbidden();
        }
    }

    public function test_padre_y_madre_reciben_cada_uno_su_copia_del_mensaje(): void
    {
        $padre = Apoderado::factory()->create();
        $padre->hijos()->attach($this->ana, ['parentesco' => 'padre']);

        app(Mensajero::class)->avisarApoderadosDe($this->ana, TipoMensaje::Salida, 'Salió del colegio', 'Ana Pérez salió a las 13:05.');

        $this->assertDatabaseCount('mensajes', 2);

        // Que la madre los marque como leídos no cambia la bandeja del padre.
        $this->actingAs($this->maria->user)->post('/familia/mensajes/leer')->assertSessionHas('ok');
        $this->assertSame(0, $this->maria->mensajes()->noLeidos()->count());
        $this->assertSame(1, $padre->mensajes()->noLeidos()->count());
    }

    public function test_ve_el_expediente_de_su_hijo_por_secciones(): void
    {
        $materia = Materia::query()->create(['nombre' => 'Matemática']);
        Nota::query()->create(['alumno_id' => $this->ana->id, 'materia_id' => $materia->id, 'anio' => 2026, 'periodo' => 1, 'nota' => 16]);
        $this->ana->asistencias()->create(['fecha' => '2026-05-14', 'hora_entrada' => '07:20:00', 'hora_salida' => '13:05:00', 'estado' => 'puntual']);
        $pension = ConceptoPago::query()->create(['nombre' => 'Pensión', 'tipo' => 'pension', 'monto' => 350]);
        Pago::query()->create(['alumno_id' => $this->ana->id, 'concepto_id' => $pension->id, 'anio' => 2026, 'mes' => 3, 'monto' => 350, 'fecha' => '2026-03-05', 'metodo' => 'yape']);

        $this->actingAs($this->maria->user);
        $base = "/familia/hijos/{$this->ana->id}";

        $this->get($base)->assertOk()->assertSee('14/05/2026')->assertSee('Entrada 07:20 · Salida 13:05');
        $this->get("{$base}?ver=asistencia&mes=2026-04")->assertOk()->assertSee('Sin registros este mes.');
        $this->get("{$base}?ver=notas")->assertOk()->assertSee('Matemática')->assertSee('Promedio 16')->assertSee('Trimestre 1');
        $this->get("{$base}?ver=pagos")->assertOk()->assertSee('pensión(es) vencida(s)')->assertSee('S/ 350.00')->assertSee('Marzo: pagada');

        foreach (['carne', 'tareas', 'horario', 'conducta', 'comunicados', 'no-existe'] as $ver) {
            $this->get("{$base}?ver={$ver}")->assertOk();
        }
    }

    public function test_ve_el_carne_de_su_hijo_con_el_modelo_del_colegio_pero_no_el_de_otros(): void
    {
        $this->ana->update(['codigo' => '2SB07', 'dni' => '91749105']);
        $padre = Apoderado::factory()->create(['nombres' => 'Henry Pérez', 'telefono' => '982306579']);
        $padre->hijos()->attach($this->ana, ['parentesco' => 'padre']);
        $ajeno = Alumno::factory()->create(['codigo' => 'AJENO1']);

        $this->actingAs($this->maria->user);

        $this->get("/familia/hijos/{$this->ana->id}?ver=carne")->assertOk()
            // Anverso
            ->assertSee('CARNÉ DE ALUMNO')
            ->assertSee('PÉREZ ANA')
            ->assertSee('2do Secundaria B')
            ->assertSee('91749105')
            ->assertSee('<svg', false)
            ->assertSee('Código QR del carné 2SB07')
            // Reverso
            ->assertSee('Este carné es personal e intransferible')
            ->assertSee('MARÍA PÉREZ - HENRY PÉREZ')
            ->assertSee('987654321')
            ->assertSee('Imprimir carné');

        $this->get("/familia/hijos/{$ajeno->id}?ver=carne")->assertForbidden();
    }

    public function test_el_administrador_imprime_el_carne_desde_el_panel(): void
    {
        $this->ana->update(['codigo' => '2SB07']);
        $this->actingAs(User::factory()->create());

        $this->get('/alumnos')->assertOk()->assertSee(route('alumnos.carne', $this->ana), false);
        $this->get("/alumnos/{$this->ana->id}/carne")->assertOk()
            ->assertSee('CARNÉ DE ALUMNO')->assertSee('PÉREZ ANA')->assertSee('Código QR del carné 2SB07')->assertSee('MARÍA PÉREZ');

        // El guardia no administra alumnos.
        $this->actingAs(User::factory()->rol(Rol::Guardia)->create());
        $this->get("/alumnos/{$this->ana->id}/carne")->assertForbidden();
    }

    public function test_el_qr_del_carne_lleva_el_codigo_del_alumno(): void
    {
        $uno = CodigoQr::svg('2SB07');

        $this->assertStringStartsWith('<svg', $uno);
        $this->assertSame($uno, CodigoQr::svg('2SB07'));
        $this->assertNotSame($uno, CodigoQr::svg('2SB08'));
    }

    public function test_sin_sesion_el_portal_lleva_al_login(): void
    {
        $this->get('/familia')->assertRedirect(route('login'));
        $this->get("/familia/hijos/{$this->ana->id}")->assertRedirect(route('login'));
    }
}
