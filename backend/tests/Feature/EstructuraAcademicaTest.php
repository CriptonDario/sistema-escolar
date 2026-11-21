<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Grado;
use App\Models\Seccion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstructuraAcademicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_grado_con_varias_secciones(): void
    {
        $this->comoRol(Rol::Admin);

        $this->postJson('/api/v1/admin/grados', [
            'nivel' => 'inicial', 'nombre' => '3 años', 'orden' => 1, 'secciones' => ['a', 'B', 'C'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.nivel', 'inicial')
            ->assertJsonCount(3, 'data.secciones')
            ->assertJsonPath('data.secciones.0.nombre', 'A');
    }

    public function test_un_grado_nuevo_nace_con_la_seccion_a(): void
    {
        $this->comoRol(Rol::Admin);

        $this->postJson('/api/v1/admin/grados', ['nivel' => 'primaria', 'nombre' => '1ro Primaria'])
            ->assertCreated()
            ->assertJsonPath('data.secciones.0.nombre', 'A');
    }

    public function test_no_admite_dos_grados_con_el_mismo_nombre_en_un_nivel(): void
    {
        $this->comoRol(Rol::Admin);
        Grado::factory()->create(['nivel' => 'primaria', 'nombre' => '1ro']);

        $this->postJson('/api/v1/admin/grados', ['nivel' => 'primaria', 'nombre' => '1ro'])
            ->assertUnprocessable()->assertJsonValidationErrors('nombre');

        // En otro nivel sí puede repetirse.
        $this->postJson('/api/v1/admin/grados', ['nivel' => 'secundaria', 'nombre' => '1ro'])->assertCreated();
    }

    public function test_agrega_la_siguiente_letra_libre(): void
    {
        $this->comoRol(Rol::Admin);
        $grado = Grado::factory()->create();
        Seccion::factory()->for($grado)->create(['nombre' => 'A']);
        Seccion::factory()->for($grado)->create(['nombre' => 'C']);

        $this->postJson("/api/v1/admin/grados/{$grado->id}/secciones")->assertCreated()->assertJsonPath('data.nombre', 'B');
        $this->postJson("/api/v1/admin/grados/{$grado->id}/secciones")->assertCreated()->assertJsonPath('data.nombre', 'D');

        $this->postJson("/api/v1/admin/grados/{$grado->id}/secciones", ['nombre' => 'a'])
            ->assertUnprocessable()->assertJsonValidationErrors('nombre');
    }

    public function test_una_seccion_con_alumnos_no_se_elimina_pero_se_puede_desactivar(): void
    {
        $this->comoRol(Rol::Admin);
        $conAlumnos = Seccion::factory()->create();
        Alumno::factory()->for($conAlumnos)->create();
        $vacia = Seccion::factory()->create();

        $this->deleteJson("/api/v1/admin/secciones/{$conAlumnos->id}")->assertStatus(409);
        $this->patchJson("/api/v1/admin/secciones/{$conAlumnos->id}", ['activo' => false])
            ->assertOk()->assertJsonPath('data.activo', false);

        $this->deleteJson("/api/v1/admin/secciones/{$vacia->id}")->assertOk();
        $this->assertDatabaseMissing('secciones', ['id' => $vacia->id]);
    }

    public function test_el_listado_sale_ordenado_por_nivel_y_cuenta_alumnos(): void
    {
        $this->comoRol(Rol::Admin);
        $this->seed(DatabaseSeeder::class);
        Alumno::factory()->count(2)->for(Seccion::query()->first())->create();

        $respuesta = $this->getJson('/api/v1/admin/grados')->assertOk()->assertJsonCount(14, 'data');

        $this->assertSame('inicial', $respuesta->json('data.0.nivel'));
        $this->assertSame('3 años', $respuesta->json('data.0.nombre'));
        $this->assertSame(2, $respuesta->json('data.0.secciones.0.alumnos'));
        $this->assertSame('5to Secundaria', $respuesta->json('data.13.nombre'));
    }

    public function test_solo_el_administrador_modifica_la_estructura(): void
    {
        foreach ([Rol::Guardia, Rol::Apoderado, Rol::Alumno, Rol::Personal] as $rol) {
            $this->comoRol($rol);
            $this->postJson('/api/v1/admin/grados', ['nivel' => 'primaria', 'nombre' => 'X'])->assertForbidden();
        }
    }
}
