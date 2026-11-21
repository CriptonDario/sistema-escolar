<?php

namespace Database\Seeders;

use App\Actions\GuardarApoderado;
use App\Enums\Rol;
use App\Enums\TipoConcepto;
use App\Enums\TipoPersonal;
use App\Models\Alumno;
use App\Models\Asignacion;
use App\Models\ConceptoPago;
use App\Models\Grado;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\Nota;
use App\Models\Personal;
use App\Models\User;
use App\Services\Cuentas\CreadorDeCuentas;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Datos de demostración para probar el sistema y la app en una computadora
 * de desarrollo. NO es para un colegio real: crea cuentas con contraseñas
 * conocidas, y por eso se niega a correr en producción.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Cuentas que deja:
 *   porteria / Guardia2026               guardia
 *   987654321 / 123456                   apoderado (la app le pide cambiarla)
 *   luis.ramos@demo.edu.pe / 123456      docente
 *   ana.perez@demo.edu.pe / 123456       alumna
 */
class DemoSeeder extends Seeder
{
    public function run(CreadorDeCuentas $cuentas, GuardarApoderado $guardarApoderado): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder crea cuentas con contraseñas conocidas: no se ejecuta en producción.');
        }

        $this->call(DatabaseSeeder::class);

        User::query()->firstOrCreate(['usuario' => 'porteria'], [
            'nombre' => 'Portería', 'password' => 'Guardia2026', 'rol' => Rol::Guardia,
        ]);

        $aula = Grado::query()->where('nombre', '2do Secundaria')->firstOrFail()->secciones()->firstOrFail();
        $anio = now()->year;

        $ana = Alumno::query()->firstOrCreate(['codigo' => 'DEMO-ALU1'], [
            'nombres' => 'Ana', 'apellidos' => 'Pérez Demo', 'seccion_id' => $aula->id,
        ]);
        if ($ana->user_id === null) {
            $cuentas->sincronizar($ana, 'ana.perez@demo.edu.pe', Rol::Alumno);
        }

        $luis = Personal::query()->firstOrCreate(['codigo' => 'DEMO-DOC1'], [
            'nombres' => 'Luis', 'apellidos' => 'Ramos Demo', 'cargo' => 'Docente de Matemática', 'tipo' => TipoPersonal::Docente,
        ]);
        if ($luis->user_id === null) {
            $cuentas->sincronizar($luis, 'luis.ramos@demo.edu.pe', Rol::Personal);
        }

        if (! User::query()->where('usuario', '987654321')->exists()) {
            $guardarApoderado->ejecutar([
                'nombres' => 'María Pérez Demo', 'telefono' => '987654321',
                'hijos' => [['alumno_id' => $ana->id, 'parentesco' => 'madre']],
            ]);
        }

        $matematica = Materia::query()->firstOrCreate(['nombre' => 'Matemática', 'nivel' => null]);

        Asignacion::query()->firstOrCreate(
            ['seccion_id' => $aula->id, 'materia_id' => $matematica->id, 'anio' => $anio],
            ['personal_id' => $luis->id],
        );

        Horario::query()->firstOrCreate(
            ['seccion_id' => $aula->id, 'dia' => 1, 'hora_inicio' => '08:00'],
            ['hora_fin' => '08:45', 'materia_id' => $matematica->id, 'personal_id' => $luis->id],
        );

        Nota::query()->firstOrCreate(
            ['alumno_id' => $ana->id, 'materia_id' => $matematica->id, 'anio' => $anio, 'periodo' => 1],
            ['nota' => 16],
        );

        ConceptoPago::query()->firstOrCreate(['nombre' => 'Pensión'], ['tipo' => TipoConcepto::Pension, 'monto' => 350]);
    }
}
