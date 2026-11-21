<?php

namespace App\Actions;

use App\Enums\EscalaCalificacion;
use App\Models\Alumno;
use App\Models\Nota;
use App\Models\Seccion;
use App\Models\User;
use App\Support\Escalas;
use Illuminate\Support\Facades\DB;

/**
 * Guarda la lista de notas de un aula en una materia y un periodo.
 * La usan el panel (administrador) y la app (docente).
 */
class GuardarNotas
{
    public function __construct(private readonly Escalas $escalas) {}

    /** Con qué escala se califica en esa aula. */
    public function escalaDe(Seccion $seccion): EscalaCalificacion
    {
        $seccion->loadMissing('grado');

        return $this->escalas->del($seccion->grado->nivel);
    }

    /**
     * @param  array<int|string, mixed>  $notas  alumno_id => valor; un valor vacío borra la nota
     * @return int cuántas notas quedaron guardadas
     */
    public function ejecutar(Seccion $seccion, int $materiaId, int $anio, int $periodo, array $notas, User $registradoPor): int
    {
        $escala = $this->escalaDe($seccion);

        // Solo se aceptan alumnos de esa aula, vengan los ids que vengan.
        $alumnosDelAula = Alumno::query()->where('seccion_id', $seccion->id)->pluck('id')->all();

        return DB::transaction(function () use ($notas, $alumnosDelAula, $materiaId, $anio, $periodo, $escala, $registradoPor) {
            $guardadas = 0;

            foreach ($notas as $alumnoId => $valor) {
                if (! in_array((int) $alumnoId, $alumnosDelAula, true)) {
                    continue;
                }

                $clave = ['alumno_id' => (int) $alumnoId, 'materia_id' => $materiaId, 'anio' => $anio, 'periodo' => $periodo];

                if ($valor === null || $valor === '') {
                    Nota::query()->where($clave)->delete();

                    continue;
                }

                // Se llena la columna de la escala y se limpia la otra, por
                // si el colegio cambió de escala a mitad de año.
                Nota::query()->updateOrCreate($clave, [
                    'nota' => $escala === EscalaCalificacion::Numerica ? $valor : null,
                    'letra' => $escala === EscalaCalificacion::Literal ? $valor : null,
                    'registrado_por' => $registradoPor->id,
                ]);
                $guardadas++;
            }

            return $guardadas;
        });
    }
}
