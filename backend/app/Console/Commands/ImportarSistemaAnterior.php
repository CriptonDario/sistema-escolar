<?php

namespace App\Console\Commands;

use App\Actions\GuardarApoderado;
use App\Enums\Nivel;
use App\Enums\Rol;
use App\Enums\TipoConcepto;
use App\Enums\TipoPersonal;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Asistencia;
use App\Models\ConceptoPago;
use App\Models\Grado;
use App\Models\Materia;
use App\Models\Nota;
use App\Models\Personal;
use App\Models\Seccion;
use App\Models\User;
use App\Support\FotoDePersona;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Trae los datos del sistema anterior (PHP sin framework) a este.
 *
 * Lee de la conexión "anterior" (variables ANTERIOR_DB_* del .env) y nunca
 * escribe en ella. Se puede ejecutar más de una vez: lo que ya existe se
 * salta, así que sirve también para traer lo nuevo antes del cambio definitivo.
 *
 * Qué trae: grados y secciones, alumnos, personal, apoderados (uno por cada
 * teléfono de apoderado de las fichas), el historial de asistencia, las
 * materias, los conceptos de pago, las notas y las fotos del carné (estas
 * últimas si ANTERIOR_RUTA_FOTOS apunta a la carpeta "uploads" anterior).
 * Qué NO trae: las contraseñas. Las cuentas nacen con la contraseña inicial
 * y cada persona crea la suya al entrar.
 */
class ImportarSistemaAnterior extends Command
{
    protected $signature = 'colegio:importar-anterior {--sin-asistencia : No traer el historial de asistencia}';

    protected $description = 'Importa grados, alumnos, personal, apoderados y asistencia del sistema anterior';

    private Connection $anterior;

    /** @var array<int, int> id de grado anterior → id de sección nueva */
    private array $seccionDe = [];

    /** @var array<int, int> id de alumno anterior → id nuevo */
    private array $alumnoDe = [];

    /** @var array<int, int> id de personal anterior → id nuevo */
    private array $personalDe = [];

    /** @var array<int, int> id de materia anterior → id nuevo */
    private array $materiaDe = [];

    private int $fotos = 0;

    public function handle(GuardarApoderado $guardarApoderado): int
    {
        try {
            $this->anterior = DB::connection('anterior');
            $this->anterior->getPdo();
        } catch (Throwable $e) {
            $this->error('No se pudo conectar con el sistema anterior. Revisa ANTERIOR_DB_* en el .env.');
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        DB::transaction(function () use ($guardarApoderado) {
            $this->table(['Dato', 'Nuevos', 'Ya existían'], [
                ['Aulas (grado + sección)', ...$this->importarAulas()],
                ['Alumnos', ...$this->importarAlumnos()],
                ['Personal', ...$this->importarPersonal()],
                ['Fotos del carné', $this->fotos, '—'],
                ['Apoderados', ...$this->importarApoderados($guardarApoderado)],
                ...($this->option('sin-asistencia') ? [] : [
                    ['Asistencias de alumnos', ...$this->importarAsistencia('asistencia_alumnos', 'alumno_id', 'alumno', $this->alumnoDe)],
                    ['Asistencias de personal', ...$this->importarAsistencia('registros_asistencia', 'personal_id', 'personal', $this->personalDe)],
                ]),
                // Tablas que no todas las instalaciones anteriores tienen.
                ...($this->hay('materias') ? [['Materias', ...$this->importarMaterias()]] : []),
                ...($this->hay('conceptos_pago') ? [['Conceptos de pago', ...$this->importarConceptos()]] : []),
                ...($this->hay('materias') && $this->hay('notas') ? [['Notas', ...$this->importarNotas()]] : []),
            ]);
        });

        $this->info('Importación terminada. Las cuentas usan la contraseña inicial: '.config('colegio.clave_inicial'));

        return self::SUCCESS;
    }

    /** En el sistema anterior cada fila de `grados` era un aula (grado + sección). */
    private function importarAulas(): array
    {
        [$nuevas, $existentes] = [0, 0];

        foreach ($this->anterior->table('grados')->orderBy('id')->get() as $fila) {
            $nivel = Nivel::tryFrom((string) $fila->nivel) ?? Nivel::Primaria;
            $nombre = $this->texto($fila->nombre);

            $grado = Grado::query()->firstOrCreate(
                ['nivel' => $nivel, 'nombre' => $nombre],
                ['orden' => (int) ($fila->orden ?? 0) ?: (int) preg_replace('/\D.*/', '', $nombre), 'activo' => $fila->estado === 'activo'],
            );

            $seccion = Seccion::query()->firstOrCreate(
                ['grado_id' => $grado->id, 'nombre' => mb_strtoupper(trim((string) $fila->seccion)) ?: 'A'],
                ['activo' => $fila->estado === 'activo'],
            );

            $seccion->wasRecentlyCreated ? $nuevas++ : $existentes++;
            $this->seccionDe[(int) $fila->id] = $seccion->id;
        }

        return [$nuevas, $existentes];
    }

    private function importarAlumnos(): array
    {
        [$nuevos, $existentes] = [0, 0];

        foreach ($this->anterior->table('alumnos')->orderBy('id')->get() as $fila) {
            $codigo = $this->texto($fila->codigo);
            $alumno = Alumno::query()->where('codigo', $codigo)->first();

            if ($alumno === null) {
                $alumno = Alumno::query()->create([
                    'codigo' => $codigo,
                    'nombres' => $this->texto($fila->nombres),
                    'apellidos' => $this->texto($fila->apellidos),
                    'dni' => $this->texto($fila->dni) ?: null,
                    'fecha_nacimiento' => $fila->fecha_nacimiento ?: null,
                    'seccion_id' => $this->seccionDe[(int) $fila->grado_id] ?? null,
                    'activo' => $fila->estado === 'activo',
                    'user_id' => $this->cuenta($fila->correo, $this->texto($fila->nombres.' '.$fila->apellidos), Rol::Alumno, $fila->estado === 'activo'),
                ]);
                $nuevos++;
            } else {
                $existentes++;
            }

            $this->traerFoto($alumno, $fila, 'alumnos');
            $this->alumnoDe[(int) $fila->id] = $alumno->id;
        }

        return [$nuevos, $existentes];
    }

    private function importarPersonal(): array
    {
        [$nuevos, $existentes] = [0, 0];

        foreach ($this->anterior->table('personal')->orderBy('id')->get() as $fila) {
            $codigo = $this->texto($fila->codigo);
            $persona = Personal::query()->where('codigo', $codigo)->first();

            if ($persona === null) {
                $persona = Personal::query()->create([
                    'codigo' => $codigo,
                    'nombres' => $this->texto($fila->nombres),
                    'apellidos' => $this->texto($fila->apellidos),
                    'cargo' => $this->texto($fila->cargo) ?: null,
                    'tipo' => TipoPersonal::tryFrom((string) ($fila->tipo_personal ?? '')) ?? TipoPersonal::Administrativo,
                    'activo' => $fila->estado === 'activo',
                    'user_id' => $this->cuenta($fila->correo, $this->texto($fila->nombres.' '.$fila->apellidos), Rol::Personal, $fila->estado === 'activo'),
                ]);
                $nuevos++;
            } else {
                $existentes++;
            }

            $this->traerFoto($persona, $fila, 'personal');
            $this->personalDe[(int) $fila->id] = $persona->id;
        }

        return [$nuevos, $existentes];
    }

    /**
     * El sistema anterior no tenía cuentas de apoderado: solo un nombre y un
     * teléfono en la ficha de cada alumno. Acá se crea una cuenta por
     * teléfono, y los hermanos con el mismo teléfono quedan bajo la misma.
     */
    private function importarApoderados(GuardarApoderado $guardar): array
    {
        [$nuevos, $existentes] = [0, 0];

        foreach ($this->anterior->table('alumnos')->where('estado', 'activo')->orderBy('id')->get() as $fila) {
            $telefono = Apoderado::normalizarTelefono((string) $fila->telefono_apoderado);
            $alumnoId = $this->alumnoDe[(int) $fila->id] ?? null;

            if (strlen($telefono) < 7 || $alumnoId === null) {
                continue;
            }

            $apoderado = Apoderado::query()->where('telefono', $telefono)->first();

            if ($apoderado === null) {
                // Un usuario con ese teléfono que no sea apoderado: no se pisa.
                if (User::query()->where('usuario', $telefono)->exists()) {
                    continue;
                }

                $apoderado = $guardar->ejecutar([
                    'nombres' => $this->texto($fila->nombre_apoderado) ?: 'Apoderado',
                    'telefono' => $telefono,
                    'correo' => filter_var(trim((string) $fila->correo_apoderado), FILTER_VALIDATE_EMAIL) ?: null,
                ]);
                $nuevos++;
            } else {
                $existentes++;
            }

            if (! $apoderado->esResponsableDe($alumnoId)) {
                $guardar->vincular($apoderado, $alumnoId);
            }
        }

        return [$nuevos, $existentes];
    }

    /**
     * @param  array<int, int>  $idNuevoDe
     */
    private function importarAsistencia(string $tabla, string $columna, string $tipoPersona, array $idNuevoDe): array
    {
        [$nuevas, $existentes] = [0, 0];

        $this->anterior->table($tabla)->orderBy('id')->chunk(500, function ($filas) use ($columna, $tipoPersona, $idNuevoDe, &$nuevas, &$existentes) {
            foreach ($filas as $fila) {
                $personaId = $idNuevoDe[(int) $fila->{$columna}] ?? null;

                if ($personaId === null || $fila->hora_entrada === null) {
                    continue;
                }

                $asistencia = Asistencia::query()->firstOrCreate(
                    ['persona_type' => $tipoPersona, 'persona_id' => $personaId, 'fecha' => $fila->fecha],
                    [
                        'hora_entrada' => $fila->hora_entrada,
                        'hora_salida' => $fila->hora_salida,
                        'estado' => in_array($fila->estado, ['puntual', 'tarde', 'falta'], true) ? $fila->estado : 'puntual',
                    ],
                );

                $asistencia->wasRecentlyCreated ? $nuevas++ : $existentes++;
            }
        });

        return [$nuevas, $existentes];
    }

    /** "ambos" (primaria y secundaria) pasa a ser una materia de todos los niveles. */
    private function importarMaterias(): array
    {
        [$nuevas, $existentes] = [0, 0];

        foreach ($this->anterior->table('materias')->orderBy('id')->get() as $fila) {
            $materia = Materia::query()->firstOrCreate(
                ['nombre' => $this->texto($fila->nombre), 'nivel' => Nivel::tryFrom((string) $fila->nivel)?->value],
                ['activo' => $fila->estado === 'activo'],
            );

            $materia->wasRecentlyCreated ? $nuevas++ : $existentes++;
            $this->materiaDe[(int) $fila->id] = $materia->id;
        }

        return [$nuevas, $existentes];
    }

    private function importarConceptos(): array
    {
        [$nuevos, $existentes] = [0, 0];

        foreach ($this->anterior->table('conceptos_pago')->orderBy('id')->get() as $fila) {
            $concepto = ConceptoPago::query()->firstOrCreate(
                ['nombre' => $this->texto($fila->nombre)],
                [
                    'tipo' => TipoConcepto::tryFrom((string) $fila->tipo) ?? TipoConcepto::Otro,
                    'monto' => (float) $fila->monto_sugerido,
                    'activo' => $fila->estado === 'activo',
                ],
            );

            $concepto->wasRecentlyCreated ? $nuevos++ : $existentes++;
        }

        return [$nuevos, $existentes];
    }

    /** Los periodos anteriores eran T1, T2 y T3. La nota "FINAL" no se trae: acá el promedio se calcula. */
    private function importarNotas(): array
    {
        [$nuevas, $existentes] = [0, 0];

        foreach ($this->anterior->table('notas')->orderBy('id')->get() as $fila) {
            $alumnoId = $this->alumnoDe[(int) $fila->alumno_id] ?? null;
            $materiaId = $this->materiaDe[(int) $fila->materia_id] ?? null;
            $periodo = ['T1' => 1, 'T2' => 2, 'T3' => 3][(string) $fila->periodo] ?? null;

            if ($alumnoId === null || $materiaId === null || $periodo === null || $fila->nota === null) {
                continue;
            }

            $nota = Nota::query()->firstOrCreate(
                ['alumno_id' => $alumnoId, 'materia_id' => $materiaId, 'anio' => (int) $fila->anio, 'periodo' => $periodo],
                ['nota' => (float) $fila->nota, 'observacion' => mb_substr($this->texto($fila->observacion), 0, 255) ?: null],
            );

            $nota->wasRecentlyCreated ? $nuevas++ : $existentes++;
        }

        return [$nuevas, $existentes];
    }

    /**
     * Copia la foto de la ficha anterior al disco privado de fotos. Solo si la
     * persona todavía no tiene foto acá, así que repetir la importación no pisa
     * una foto subida después.
     */
    private function traerFoto(Alumno|Personal $persona, object $fila, string $carpeta): void
    {
        $carpetaAnterior = (string) config('colegio.anterior_fotos');
        $archivo = basename((string) ($fila->foto ?? ''));

        if ($carpetaAnterior === '' || $persona->foto !== null || $archivo === '' || $archivo === 'default.png') {
            return;
        }

        $origen = rtrim($carpetaAnterior, '/\\').DIRECTORY_SEPARATOR.$archivo;

        if (! is_file($origen) || @getimagesize($origen) === false) {
            return;
        }

        $persona->update(['foto' => Storage::disk(FotoDePersona::DISCO)->putFile("fotos/{$carpeta}", new File($origen))]);
        $this->fotos++;
    }

    private function hay(string $tabla): bool
    {
        return $this->anterior->getSchemaBuilder()->hasTable($tabla);
    }

    /** Crea la cuenta de acceso si la ficha tenía un correo válido y libre. */
    private function cuenta(?string $correo, string $nombre, Rol $rol, bool $activo): ?int
    {
        $correo = mb_strtolower(trim((string) $correo));

        if (! filter_var($correo, FILTER_VALIDATE_EMAIL) || User::query()->where('usuario', $correo)->exists()) {
            return null;
        }

        return User::query()->create([
            'nombre' => $nombre,
            'usuario' => $correo,
            'password' => config('colegio.clave_inicial'),
            'rol' => $rol,
            'debe_cambiar_clave' => true,
            'activo' => $activo,
        ])->id;
    }

    /**
     * El sistema anterior guardaba los textos escapados para HTML
     * ("D&#039;Angelo"): acá se devuelven a texto plano.
     */
    private function texto(mixed $valor): string
    {
        return trim(html_entity_decode((string) $valor, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
