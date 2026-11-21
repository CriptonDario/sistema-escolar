<?php

namespace App\Services\Academico;

use App\Enums\EscalaCalificacion;
use App\Models\Alumno;
use App\Models\Comunicado;
use App\Models\Conducta;
use App\Models\Horario;
use App\Models\Nota;
use App\Models\Tarea;
use App\Support\Escalas;
use App\Support\Periodos;

/**
 * Lo que la app muestra de un alumno: notas, horario, tareas y conducta.
 * Lo consultan por igual el apoderado (de su hijo) y el propio alumno.
 */
class Expediente
{
    public function __construct(
        private readonly Periodos $periodos,
        private readonly Escalas $escalas,
    ) {}

    /**
     * Notas del año por materia, con una columna por periodo. El promedio solo
     * existe en la escala numérica: las letras no se promedian.
     */
    public function notas(Alumno $alumno, int $anio): array
    {
        $porMateria = Nota::query()
            ->where('alumno_id', $alumno->id)->where('anio', $anio)
            ->with('materia')
            ->get()
            ->groupBy('materia_id');

        $materias = $porMateria->map(function ($notas) {
            $porPeriodo = [];
            foreach (array_keys($this->periodos->todos()) as $periodo) {
                $porPeriodo[$periodo] = $notas->firstWhere('periodo', $periodo)?->valor();
            }
            $registradas = array_filter($porPeriodo, fn ($nota) => is_numeric($nota));

            return [
                'materia' => $notas->first()->materia->nombre,
                'periodos' => $porPeriodo,
                'promedio' => $registradas === [] ? null : round(array_sum($registradas) / count($registradas), 1),
            ];
        })->sortBy('materia')->values()->all();

        $alumno->loadMissing('seccion.grado');
        $escala = $alumno->seccion ? $this->escalas->del($alumno->seccion->grado->nivel) : EscalaCalificacion::Numerica;

        return [
            'anio' => $anio,
            'escala' => $escala->value,
            'letras' => $escala === EscalaCalificacion::Literal ? EscalaCalificacion::LETRAS : null,
            'periodos' => $this->periodos->todos(),
            'materias' => $materias,
        ];
    }

    /** Horario semanal del aula del alumno, agrupado por día. */
    public function horario(Alumno $alumno): array
    {
        if ($alumno->seccion_id === null) {
            return [];
        }

        return Horario::query()
            ->where('seccion_id', $alumno->seccion_id)
            ->with(['materia', 'personal'])
            ->orderBy('dia')->orderBy('hora_inicio')
            ->get()
            ->groupBy('dia')
            ->map(fn ($bloques, $dia) => [
                'dia' => (int) $dia,
                'nombre' => Horario::DIAS[$dia] ?? '',
                'bloques' => $bloques->map(fn (Horario $bloque) => [
                    'inicio' => substr($bloque->hora_inicio, 0, 5),
                    'fin' => substr($bloque->hora_fin, 0, 5),
                    'nombre' => $bloque->nombre(),
                    'docente' => $bloque->personal?->nombreCompleto(),
                ])->values()->all(),
            ])->values()->all();
    }

    /** Tareas, exámenes y actividades del aula: las próximas y las de los últimos 15 días. */
    public function tareas(Alumno $alumno): array
    {
        if ($alumno->seccion_id === null) {
            return [];
        }

        return Tarea::query()->vigentes()
            ->where('seccion_id', $alumno->seccion_id)
            ->whereDate('fecha_entrega', '>=', today()->subDays(15))
            ->with('materia')
            ->orderBy('fecha_entrega')
            ->get()
            ->map(fn (Tarea $tarea) => [
                'id' => $tarea->id,
                'tipo' => $tarea->tipo->value,
                'titulo' => $tarea->titulo,
                'descripcion' => $tarea->descripcion,
                'materia' => $tarea->materia?->nombre,
                'fecha_entrega' => $tarea->fecha_entrega->toDateString(),
                'vencida' => $tarea->fecha_entrega->isBefore(today()),
            ])->all();
    }

    public function conducta(Alumno $alumno, int $anio): array
    {
        return Conducta::query()->vigentes()
            ->where('alumno_id', $alumno->id)
            ->whereYear('fecha', $anio)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->get()
            ->map(fn (Conducta $registro) => [
                'id' => $registro->id,
                'tipo' => $registro->tipo->value,
                'tipo_etiqueta' => $registro->tipo->etiqueta(),
                'descripcion' => $registro->descripcion,
                'fecha' => $registro->fecha->toDateString(),
            ])->all();
    }

    /** Los comunicados recientes que le corresponden al alumno. */
    public function comunicados(Alumno $alumno): array
    {
        $alumno->loadMissing('seccion.grado');
        $nivel = $alumno->seccion?->grado->nivel->value;

        return Comunicado::query()
            ->where(function ($q) use ($alumno, $nivel) {
                $q->where('alcance', 'todos')
                    ->orWhere(fn ($n) => $n->where('alcance', 'nivel')->where('nivel', $nivel))
                    ->orWhere(fn ($s) => $s->where('alcance', 'seccion')->where('seccion_id', $alumno->seccion_id));
            })
            ->latest()->limit(30)->get()
            ->map(fn (Comunicado $comunicado) => [
                'id' => $comunicado->id,
                'titulo' => $comunicado->titulo,
                'cuerpo' => $comunicado->cuerpo,
                'fecha' => $comunicado->created_at->toIso8601String(),
            ])->all();
    }
}
