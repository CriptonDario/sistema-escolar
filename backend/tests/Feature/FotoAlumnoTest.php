<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FotoAlumnoTest extends TestCase
{
    use RefreshDatabase;

    private Alumno $ana;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');

        $this->ana = Alumno::factory()->create(['codigo' => '2SB07', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
    }

    /** @return array<string, mixed> */
    private function datos(array $extra = []): array
    {
        return $extra + ['_method' => 'PUT', 'codigo' => '2SB07', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'activo' => '1'];
    }

    private function subirFoto(): string
    {
        $this->actingAs(User::factory()->create())
            ->post("/alumnos/{$this->ana->id}", $this->datos(['foto' => UploadedFile::fake()->image('ana.jpg', 300, 380)]))
            ->assertSessionHasNoErrors()->assertRedirect(route('alumnos.index'));

        return $this->ana->refresh()->foto;
    }

    public function test_el_administrador_sube_la_foto_y_sale_en_el_carne(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get("/alumnos/{$this->ana->id}/edit")->assertOk()->assertSee('Foto del carné')->assertSee('multipart/form-data', false);

        $ruta = $this->subirFoto();

        $this->assertStringStartsWith('fotos/alumnos/', $ruta);
        Storage::disk('local')->assertExists($ruta);

        $this->get("/alumnos/{$this->ana->id}/edit")->assertOk()->assertSee('Cambiar foto')->assertSee('Quitar la foto');
        $this->get("/alumnos/{$this->ana->id}/carne")->assertOk()->assertSee(route('alumnos.foto', $this->ana), false);
        $this->get(route('alumnos.foto', $this->ana))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_al_cambiarla_o_quitarla_se_borra_el_archivo_anterior(): void
    {
        $primera = $this->subirFoto();
        $segunda = $this->subirFoto();

        $this->assertNotSame($primera, $segunda);
        Storage::disk('local')->assertMissing($primera);

        // Guardar sin elegir archivo mantiene la actual.
        $this->post("/alumnos/{$this->ana->id}", $this->datos())->assertSessionHasNoErrors();
        $this->assertSame($segunda, $this->ana->refresh()->foto);

        $this->post("/alumnos/{$this->ana->id}", $this->datos(['quitar_foto' => '1']))->assertSessionHasNoErrors();
        $this->assertNull($this->ana->refresh()->foto);
        Storage::disk('local')->assertMissing($segunda);
        $this->get(route('alumnos.foto', $this->ana))->assertNotFound();
    }

    public function test_solo_se_aceptan_imagenes_de_hasta_3_mb(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post("/alumnos/{$this->ana->id}", $this->datos(['foto' => UploadedFile::fake()->create('virus.php', 10, 'text/x-php')]))
            ->assertSessionHasErrors('foto');
        $this->post("/alumnos/{$this->ana->id}", $this->datos(['foto' => UploadedFile::fake()->create('notas.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors('foto');
        $this->post("/alumnos/{$this->ana->id}", $this->datos(['foto' => UploadedFile::fake()->image('enorme.jpg')->size(4000)]))
            ->assertSessionHasErrors('foto');

        $this->assertNull($this->ana->refresh()->foto);
    }

    public function test_la_foto_solo_la_ven_el_colegio_su_apoderado_y_el_propio_alumno(): void
    {
        $this->subirFoto();
        $url = route('alumnos.foto', $this->ana);

        $madre = Apoderado::factory()->create(['user_id' => User::factory()->rol(Rol::Apoderado)->create()]);
        $madre->hijos()->attach($this->ana, ['parentesco' => 'madre']);
        $otroPadre = Apoderado::factory()->create(['user_id' => User::factory()->rol(Rol::Apoderado)->create()]);
        $cuentaDeAna = User::factory()->rol(Rol::Alumno)->create();
        $this->ana->update(['user_id' => $cuentaDeAna->id]);

        $this->actingAs($madre->user)->get($url)->assertOk();
        $this->actingAs($cuentaDeAna)->get($url)->assertOk();
        $this->actingAs(User::factory()->rol(Rol::Guardia)->create())->get($url)->assertOk();

        $this->actingAs($otroPadre->user)->get($url)->assertForbidden();
        $this->actingAs(User::factory()->rol(Rol::Alumno)->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->rol(Rol::Personal)->create())->get($url)->assertForbidden();
    }

    public function test_sin_sesion_no_se_ve_la_foto(): void
    {
        $this->subirFoto();
        $this->app['auth']->forgetGuards();

        $this->get(route('alumnos.foto', $this->ana))->assertRedirect(route('login'));
        $this->getJson("/api/v1/alumnos/{$this->ana->id}/foto")->assertUnauthorized();
    }

    public function test_la_app_recibe_la_direccion_de_la_foto_y_la_descarga_con_su_token(): void
    {
        $this->subirFoto();
        $this->app['auth']->forgetGuards();

        $madre = Apoderado::factory()->create(['user_id' => User::factory()->rol(Rol::Apoderado)->create()]);
        $madre->hijos()->attach($this->ana, ['parentesco' => 'madre']);
        Sanctum::actingAs($madre->user);

        $foto = $this->getJson('/api/v1/hijos')->assertOk()->json('data.0.foto');

        $this->assertStringContainsString("/api/v1/alumnos/{$this->ana->id}/foto", $foto);
        $this->get($foto)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }
}
