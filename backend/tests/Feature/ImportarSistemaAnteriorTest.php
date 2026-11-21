<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Asistencia;
use App\Models\Personal;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportarSistemaAnteriorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Un "sistema anterior" en miniatura, con sus tablas y sus datos.
        config(['database.connections.anterior' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('anterior');
        $viejo = Schema::connection('anterior');

        $viejo->create('grados', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('nivel');
            $t->integer('orden')->default(0);
            $t->string('seccion');
            $t->string('estado');
        });
        $viejo->create('alumnos', function (Blueprint $t) {
            $t->id();
            $t->string('codigo');
            $t->string('nombres');
            $t->string('apellidos');
            $t->string('dni')->nullable();
            $t->date('fecha_nacimiento')->nullable();
            $t->integer('grado_id')->nullable();
            $t->string('correo')->nullable();
            $t->string('nombre_apoderado')->nullable();
            $t->string('telefono_apoderado')->nullable();
            $t->string('correo_apoderado')->nullable();
            $t->string('estado');
        });
        $viejo->create('personal', function (Blueprint $t) {
            $t->id();
            $t->string('codigo');
            $t->string('nombres');
            $t->string('apellidos');
            $t->string('correo')->nullable();
            $t->string('cargo')->nullable();
            $t->string('tipo_personal')->nullable();
            $t->string('estado');
        });
        foreach (['asistencia_alumnos' => 'alumno_id', 'registros_asistencia' => 'personal_id'] as $tabla => $columna) {
            $viejo->create($tabla, function (Blueprint $t) use ($columna) {
                $t->id();
                $t->integer($columna);
                $t->date('fecha');
                $t->time('hora_entrada')->nullable();
                $t->time('hora_salida')->nullable();
                $t->string('estado');
            });
        }

        $db = DB::connection('anterior');
        $db->table('grados')->insert([
            ['id' => 7, 'nombre' => '2do Secundaria', 'nivel' => 'secundaria', 'orden' => 2, 'seccion' => 'A', 'estado' => 'activo'],
            ['id' => 8, 'nombre' => '2do Secundaria', 'nivel' => 'secundaria', 'orden' => 2, 'seccion' => 'B', 'estado' => 'activo'],
        ]);
        $db->table('alumnos')->insert([
            ['id' => 10, 'codigo' => '2SA01', 'nombres' => 'Ana', 'apellidos' => 'D&#039;Angelo', 'grado_id' => 7, 'correo' => 'ana@colegio.edu.pe',
                'nombre_apoderado' => 'Mar&iacute;a P&eacute;rez', 'telefono_apoderado' => '+51 987 654 321', 'correo_apoderado' => null, 'estado' => 'activo'],
            ['id' => 11, 'codigo' => '2SB01', 'nombres' => 'Luis', 'apellidos' => 'D&#039;Angelo', 'grado_id' => 8, 'correo' => null,
                'nombre_apoderado' => 'Mar&iacute;a P&eacute;rez', 'telefono_apoderado' => '987654321', 'correo_apoderado' => null, 'estado' => 'activo'],
            ['id' => 12, 'codigo' => 'BAJA1', 'nombres' => 'Retirado', 'apellidos' => 'Ya No', 'grado_id' => 7, 'correo' => null,
                'nombre_apoderado' => null, 'telefono_apoderado' => null, 'correo_apoderado' => null, 'estado' => 'inactivo'],
        ]);
        $db->table('personal')->insert([
            ['id' => 3, 'codigo' => 'DOC01', 'nombres' => 'Luis', 'apellidos' => 'Ramos', 'correo' => 'luis@colegio.edu.pe', 'cargo' => 'Docente', 'tipo_personal' => 'docente', 'estado' => 'activo'],
        ]);
        $db->table('asistencia_alumnos')->insert([
            ['alumno_id' => 10, 'fecha' => '2026-03-10', 'hora_entrada' => '07:20:00', 'hora_salida' => '13:05:00', 'estado' => 'puntual'],
            ['alumno_id' => 10, 'fecha' => '2026-03-11', 'hora_entrada' => '07:45:00', 'hora_salida' => null, 'estado' => 'tarde'],
        ]);
        $db->table('registros_asistencia')->insert([
            ['personal_id' => 3, 'fecha' => '2026-03-10', 'hora_entrada' => '07:00:00', 'hora_salida' => '14:00:00', 'estado' => 'puntual'],
        ]);
    }

    public function test_importa_aulas_personas_apoderados_y_asistencia(): void
    {
        $this->artisan('colegio:importar-anterior')->assertSuccessful();

        // Dos filas del sistema anterior = un grado con dos secciones.
        $this->assertDatabaseCount('grados', 1);
        $this->assertSame(['A', 'B'], Seccion::query()->orderBy('nombre')->pluck('nombre')->all());

        // Los textos llegan limpios y el alumno queda en su aula.
        $ana = Alumno::query()->where('codigo', '2SA01')->firstOrFail();
        $this->assertSame("D'Angelo", $ana->apellidos);
        $this->assertSame('2do Secundaria A', $ana->aula());
        $this->assertFalse(Alumno::query()->where('codigo', 'BAJA1')->firstOrFail()->activo);

        // Con correo hay cuenta, con la contraseña inicial y obligada a cambiarla.
        $this->assertSame('ana@colegio.edu.pe', $ana->user->usuario);
        $this->assertTrue($ana->user->debe_cambiar_clave);
        $this->assertTrue(Hash::check('123456', $ana->user->password));
        $this->assertNull(Alumno::query()->where('codigo', '2SB01')->firstOrFail()->user_id);
        $this->assertSame('luis@colegio.edu.pe', Personal::query()->firstOrFail()->user->usuario);

        // Los dos hermanos comparten teléfono: una sola cuenta de apoderado.
        $this->assertDatabaseCount('apoderados', 1);
        $apoderado = Apoderado::query()->firstOrFail();
        $this->assertSame('María Pérez', $apoderado->nombres);
        $this->assertSame('987654321', $apoderado->telefono);
        $this->assertSame(2, $apoderado->hijos()->count());

        $this->assertSame(2, $ana->asistencias()->count());
        $this->assertDatabaseHas('asistencias', ['persona_type' => 'personal', 'hora_salida' => '14:00:00']);
    }

    public function test_trae_materias_conceptos_notas_y_fotos_cuando_el_sistema_anterior_los_tiene(): void
    {
        Storage::fake('local');
        $viejo = Schema::connection('anterior');
        $viejo->create('materias', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('nivel');
            $t->string('estado');
        });
        $viejo->create('conceptos_pago', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('tipo');
            $t->decimal('monto_sugerido', 8, 2);
            $t->string('estado');
        });
        $viejo->create('notas', function (Blueprint $t) {
            $t->id();
            $t->integer('alumno_id');
            $t->integer('materia_id');
            $t->string('periodo');
            $t->integer('anio');
            $t->decimal('nota', 4, 1)->nullable();
            $t->text('observacion')->nullable();
        });
        $viejo->table('personal', fn (Blueprint $t) => $t->string('foto')->nullable());
        $viejo->table('alumnos', fn (Blueprint $t) => $t->string('foto')->nullable());

        $db = DB::connection('anterior');
        $alumno = $db->table('alumnos')->orderBy('id')->first();
        $persona = $db->table('personal')->orderBy('id')->first();
        $db->table('materias')->insert([
            ['id' => 1, 'nombre' => 'Matemática', 'nivel' => 'ambos', 'estado' => 'activo'],
            ['id' => 2, 'nombre' => 'Química', 'nivel' => 'secundaria', 'estado' => 'inactivo'],
        ]);
        $db->table('conceptos_pago')->insert([
            ['nombre' => 'Matricula', 'tipo' => 'matricula', 'monto_sugerido' => 350, 'estado' => 'activo'],
            ['nombre' => 'Uniforme', 'tipo' => 'producto', 'monto_sugerido' => 120, 'estado' => 'activo'],
        ]);
        $db->table('notas')->insert([
            ['alumno_id' => $alumno->id, 'materia_id' => 1, 'periodo' => 'T1', 'anio' => 2025, 'nota' => 16.5, 'observacion' => null],
            ['alumno_id' => $alumno->id, 'materia_id' => 1, 'periodo' => 'FINAL', 'anio' => 2025, 'nota' => 17, 'observacion' => null],
            ['alumno_id' => 9999, 'materia_id' => 1, 'periodo' => 'T2', 'anio' => 2025, 'nota' => 12, 'observacion' => null],
        ]);

        // La carpeta "uploads" anterior, con una foto de verdad y un archivo que no es imagen.
        $uploads = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uploads_prueba_'.uniqid();
        mkdir($uploads);
        $imagen = UploadedFile::fake()->image('x.png', 60, 80);
        copy($imagen->getPathname(), $uploads.DIRECTORY_SEPARATOR.'foto_1.png');
        file_put_contents($uploads.DIRECTORY_SEPARATOR.'falsa.png', '<?php echo 1;');
        config(['colegio.anterior_fotos' => $uploads]);
        $db->table('personal')->where('id', $persona->id)->update(['foto' => 'foto_1.png']);
        $db->table('alumnos')->where('id', $alumno->id)->update(['foto' => '../falsa.png']);
        $db->table('alumnos')->where('id', '!=', $alumno->id)->update(['foto' => 'default.png']);

        $this->artisan('colegio:importar-anterior')->assertSuccessful();
        $this->artisan('colegio:importar-anterior')->assertSuccessful();

        $this->assertDatabaseHas('materias', ['nombre' => 'Matemática', 'nivel' => null, 'activo' => true]);
        $this->assertDatabaseHas('materias', ['nombre' => 'Química', 'nivel' => 'secundaria', 'activo' => false]);
        $this->assertDatabaseCount('materias', 2);
        $this->assertDatabaseHas('conceptos_pago', ['nombre' => 'Matricula', 'tipo' => 'matricula', 'monto' => 350]);
        $this->assertDatabaseHas('conceptos_pago', ['nombre' => 'Uniforme', 'tipo' => 'otro']);
        // Solo la nota del trimestre de un alumno que existe.
        $this->assertDatabaseCount('notas', 1);
        $this->assertDatabaseHas('notas', ['anio' => 2025, 'periodo' => 1, 'nota' => 16.5]);

        $foto = Personal::query()->where('codigo', $persona->codigo)->value('foto');
        $this->assertStringStartsWith('fotos/personal/', $foto);
        Storage::disk('local')->assertExists($foto);
        $this->assertSame(0, Alumno::query()->whereNotNull('foto')->count());

        array_map('unlink', glob($uploads.DIRECTORY_SEPARATOR.'*'));
        rmdir($uploads);
    }

    public function test_repetir_la_importacion_no_duplica_nada(): void
    {
        $this->artisan('colegio:importar-anterior')->assertSuccessful();
        $antes = [Alumno::query()->count(), Personal::query()->count(), Apoderado::query()->count(), Asistencia::query()->count(), User::query()->count()];

        $this->artisan('colegio:importar-anterior')->assertSuccessful();

        $this->assertSame($antes, [Alumno::query()->count(), Personal::query()->count(), Apoderado::query()->count(), Asistencia::query()->count(), User::query()->count()]);
    }

    public function test_puede_omitir_el_historial_de_asistencia(): void
    {
        $this->artisan('colegio:importar-anterior --sin-asistencia')->assertSuccessful();

        $this->assertDatabaseCount('asistencias', 0);
        $this->assertDatabaseCount('alumnos', 3);
    }

    public function test_avisa_si_no_puede_conectar_con_el_sistema_anterior(): void
    {
        config(['database.connections.anterior' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x']]);
        DB::purge('anterior');

        $this->artisan('colegio:importar-anterior')->assertFailed();
        $this->assertDatabaseCount('alumnos', 0);
    }
}
