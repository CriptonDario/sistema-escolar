<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Enums\TipoPersonal;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Grado;
use App\Models\Personal;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Todos tienen carné: alumnos, docentes y trabajadores. */
class CarnePersonalTest extends TestCase
{
    use RefreshDatabase;

    private Personal $luis;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');

        $this->luis = Personal::factory()->create([
            'codigo' => 'DOC01', 'nombres' => 'Luis', 'apellidos' => 'Ramos', 'dni' => '40123456',
            'cargo' => 'Docente de Matemática', 'tipo' => TipoPersonal::Docente,
            'user_id' => User::factory()->rol(Rol::Personal)->create(['nombre' => 'Luis Ramos']),
        ]);
    }

    /** @return array<string, mixed> */
    private function datos(array $extra = []): array
    {
        return $extra + ['_method' => 'PUT', 'codigo' => 'DOC01', 'nombres' => 'Luis', 'apellidos' => 'Ramos', 'tipo' => 'docente', 'activo' => '1'];
    }

    public function test_el_administrador_ve_e_imprime_el_carne_de_un_docente(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/personal')->assertOk()->assertSee(route('personal.carne', $this->luis), false);
        $this->get("/personal/{$this->luis->id}/carne")->assertOk()
            ->assertSee('CARNÉ DE DOCENTE')
            ->assertSee('RAMOS LUIS')
            ->assertSee('Docente de Matemática')
            ->assertSee('40123456')
            ->assertSee('Código QR del carné DOC01')
            ->assertSee('Este carné es personal e intransferible');
    }

    public function test_los_trabajadores_que_no_son_docentes_tambien_tienen_carne(): void
    {
        $rosa = Personal::factory()->create(['codigo' => 'ADM01', 'nombres' => 'Rosa', 'apellidos' => 'Quispe', 'cargo' => null, 'tipo' => TipoPersonal::Apoyo]);

        $this->actingAs(User::factory()->create())->get("/personal/{$rosa->id}/carne")->assertOk()
            ->assertSee('CARNÉ DE PERSONAL')->assertSee('QUISPE ROSA')->assertSee('Apoyo')->assertSee('Código QR del carné ADM01');
    }

    public function test_se_imprimen_en_lote_los_carnes_de_un_aula_de_todos_los_alumnos_y_del_personal(): void
    {
        $aulaA = Seccion::factory()->for(Grado::factory()->create(['nivel' => 'primaria', 'nombre' => '1ro Primaria']))->create(['nombre' => 'A']);
        $aulaB = Seccion::factory()->for($aulaA->grado)->create(['nombre' => 'B']);
        Alumno::factory()->for($aulaA)->create(['codigo' => '1PA01', 'nombres' => 'Sol', 'apellidos' => 'Abado']);
        Alumno::factory()->for($aulaA)->create(['codigo' => '1PA02', 'nombres' => 'Iker', 'apellidos' => 'Zapata', 'activo' => false]);
        Alumno::factory()->for($aulaB)->create(['codigo' => '1PB01', 'nombres' => 'Luz', 'apellidos' => 'Mamani']);

        $this->actingAs(User::factory()->create());

        $this->get('/alumnos')->assertOk()->assertSee('Imprimir todos los carnés');
        $this->get("/alumnos?seccion_id={$aulaA->id}")->assertOk()->assertSee('Imprimir carnés de esta aula');

        // Un aula: solo sus alumnos activos.
        $this->get("/carnes?seccion_id={$aulaA->id}")->assertOk()
            ->assertSee('Carnés de 1ro Primaria A')->assertSee('ABADO SOL')
            ->assertDontSee('MAMANI LUZ')->assertDontSee('ZAPATA IKER');

        $this->get('/carnes')->assertOk()->assertSee('Carnés de todos los alumnos')->assertSee('ABADO SOL')->assertSee('MAMANI LUZ');

        $this->get('/carnes?de=personal')->assertOk()->assertSee('Carnés del personal')->assertSee('RAMOS LUIS')->assertDontSee('ABADO SOL');
        $this->get('/personal')->assertOk()->assertSee(route('carnes.lote', ['de' => 'personal']), false);

        $this->actingAs(User::factory()->rol(Rol::Guardia)->create())->get('/carnes')->assertForbidden();
    }

    public function test_el_guardia_no_administra_los_carnes_del_personal(): void
    {
        $this->actingAs(User::factory()->rol(Rol::Guardia)->create())->get("/personal/{$this->luis->id}/carne")->assertForbidden();
    }

    public function test_se_sube_la_foto_del_trabajador_y_sale_en_su_carne(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get("/personal/{$this->luis->id}/edit")->assertOk()->assertSee('Foto del carné')->assertSee('multipart/form-data', false);

        $this->post("/personal/{$this->luis->id}", $this->datos(['foto' => UploadedFile::fake()->image('luis.png', 300, 380)]))
            ->assertSessionHasNoErrors()->assertRedirect(route('personal.index'));

        $ruta = $this->luis->refresh()->foto;
        $this->assertStringStartsWith('fotos/personal/', $ruta);
        Storage::disk('local')->assertExists($ruta);

        $this->get("/personal/{$this->luis->id}/carne")->assertOk()->assertSee(route('personal.foto', $this->luis), false);
        $this->get(route('personal.foto', $this->luis))->assertOk()->assertHeader('Content-Type', 'image/png');

        // Un archivo que no es imagen no entra.
        $this->post("/personal/{$this->luis->id}", $this->datos(['foto' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf')]))
            ->assertSessionHasErrors('foto');

        $this->post("/personal/{$this->luis->id}", $this->datos(['quitar_foto' => '1']))->assertSessionHasNoErrors();
        $this->assertNull($this->luis->refresh()->foto);
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_la_foto_del_trabajador_la_ven_el_colegio_y_el_mismo(): void
    {
        $this->actingAs(User::factory()->create())
            ->post("/personal/{$this->luis->id}", $this->datos(['foto' => UploadedFile::fake()->image('luis.jpg')]))->assertSessionHasNoErrors();
        $url = route('personal.foto', $this->luis);

        $this->actingAs($this->luis->user)->get($url)->assertOk();
        $this->actingAs(User::factory()->rol(Rol::Direccion)->create())->get($url)->assertOk();

        $this->actingAs(User::factory()->rol(Rol::Personal)->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->rol(Rol::Apoderado)->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->rol(Rol::Alumno)->create())->get($url)->assertForbidden();
    }

    public function test_la_app_recibe_los_datos_del_carne_del_docente(): void
    {
        Sanctum::actingAs($this->luis->user);

        $this->getJson('/api/v1/perfil')->assertOk()
            ->assertJsonPath('data.codigo', 'DOC01')
            ->assertJsonPath('data.carne.titulo', 'CARNÉ DE DOCENTE')
            ->assertJsonPath('data.carne.nombre', 'Ramos Luis')
            ->assertJsonPath('data.carne.detalle_etiqueta', 'Cargo')
            ->assertJsonPath('data.carne.detalle', 'Docente de Matemática')
            ->assertJsonPath('data.carne.dni', '40123456')
            ->assertJsonPath('data.carne.foto', null);
    }

    public function test_la_app_recibe_los_datos_del_carne_del_alumno(): void
    {
        $aula = Seccion::factory()->for(Grado::factory()->create(['nivel' => 'primaria', 'nombre' => '1ro Primaria']))->create(['nombre' => 'A']);
        $sol = Alumno::factory()->for($aula)->create([
            'codigo' => '1PA01', 'nombres' => 'Sol', 'apellidos' => 'Abado', 'dni' => '91749105',
            'user_id' => User::factory()->rol(Rol::Alumno)->create(),
        ]);
        Apoderado::factory()->create(['nombres' => 'Soledad Huanca', 'telefono' => '982306579'])->hijos()->attach($sol, ['parentesco' => 'madre']);

        Sanctum::actingAs($sol->user);

        $this->getJson('/api/v1/perfil')->assertOk()
            ->assertJsonPath('data.carne.titulo', 'CARNÉ DE ALUMNO')
            ->assertJsonPath('data.carne.nombre', 'Abado Sol')
            ->assertJsonPath('data.carne.detalle', '1ro Primaria A')
            ->assertJsonPath('data.carne.dni', '91749105')
            ->assertJsonPath('data.carne.apoderados', 'Soledad Huanca')
            ->assertJsonPath('data.carne.telefono', '982306579');
    }

    public function test_quien_no_tiene_carne_no_recibe_datos_de_carne(): void
    {
        Sanctum::actingAs(User::factory()->rol(Rol::Guardia)->create());

        $this->getJson('/api/v1/perfil')->assertOk()->assertJsonPath('data.carne', null);
    }
}
