<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\GuardarNotas;
use App\Actions\PublicarTarea;
use App\Actions\RegistrarConducta;
use App\Enums\EscalaCalificacion;
use App\Enums\TipoConducta;
use App\Enums\TipoTarea;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Asignacion;
use App\Models\Horario;
use App\Models\Nota;
use App\Models\Personal;
use App\Models\Seccion;
use App\Support\Periodos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Lo que un docente hace desde la app. Todo está limitado a las aulas y
 * materias que el colegio le asignó en el año en curso.
 */
class DocenteController extends Controller
{
    /** Sus aulas y materias de este año. */
    public function aulas(Request $request): JsonResponse
    {
        $asignaciones = Asignacion::query()
            ->where('personal_id', $this->docente($request)->id)->where('anio', now()->year)
            ->with(['seccion.grado', 'materia'])
            ->get()
            ->map(fn (Asignacion $asignacion) => [
                'seccion_id' => $asignacion->seccion_id,
                'aula' => $asignacion->seccion->nombreCompleto(),
                'nivel' => $asignacion->seccion->grado->nivel->value,
                'materia_id' => $asignacion->materia_id,
                'materia' => $asignacion->materia->nombre,
            ])
            ->sortBy(['aula', 'materia'])->values();

        return response()->json(['anio' => now()->year, 'aulas' => $asignaciones]);
    }

    /** La lista del aula con la nota de cada alumno en una materia y un periodo. */
    public function notas(Request $request, Seccion $seccion, Periodos $periodos, GuardarNotas $guardar): JsonResponse
    {
        $this->exigirAsignacion($request, $seccion->id, (int) $request->input('materia_id'));

        $datos = $request->validate([
            'materia_id' => ['required', 'integer'],
            'periodo' => ['nullable', 'integer', 'between:1,'.$periodos->cantidad()],
        ]);
        $periodo = (int) ($datos['periodo'] ?? 1);

        $alumnos = Alumno::query()->activos()->where('seccion_id', $seccion->id)->orderBy('apellidos')->orderBy('nombres')->get();
        $notas = Nota::query()
            ->whereIn('alumno_id', $alumnos->modelKeys())
            ->where(['materia_id' => $datos['materia_id'], 'anio' => now()->year, 'periodo' => $periodo])
            ->get()->keyBy('alumno_id');
        $escala = $guardar->escalaDe($seccion);

        return response()->json([
            'aula' => $seccion->nombreCompleto(),
            'periodo' => $periodo,
            'periodos' => $periodos->todos(),
            'escala' => $escala->value,
            'letras' => $escala === EscalaCalificacion::Literal ? EscalaCalificacion::LETRAS : null,
            'nota_maxima' => EscalaCalificacion::NOTA_MAXIMA,
            'alumnos' => $alumnos->map(fn (Alumno $alumno) => [
                'id' => $alumno->id,
                'codigo' => $alumno->codigo,
                'nombre' => $alumno->apellidos.', '.$alumno->nombres,
                'nota' => $notas->get($alumno->id)?->valor(),
            ]),
        ]);
    }

    public function guardarNotas(Request $request, Seccion $seccion, Periodos $periodos, GuardarNotas $guardar): JsonResponse
    {
        $this->exigirAsignacion($request, $seccion->id, (int) $request->input('materia_id'));

        $datos = $request->validate([
            'materia_id' => ['required', 'integer'],
            'periodo' => ['required', 'integer', 'between:1,'.$periodos->cantidad()],
            'notas' => ['required', 'array'],
            'notas.*' => $guardar->escalaDe($seccion)->reglas(),
        ], [
            'notas.*.between' => 'Las notas van de 0 a '.EscalaCalificacion::NOTA_MAXIMA.'.',
            'notas.*.in' => 'La calificación debe ser AD, A, B o C.',
        ]);

        $guardadas = $guardar->ejecutar($seccion, (int) $datos['materia_id'], now()->year, (int) $datos['periodo'], $datos['notas'], $request->user());

        return response()->json(['message' => 'Notas guardadas.', 'guardadas' => $guardadas]);
    }

    public function publicarTarea(Request $request, Seccion $seccion, PublicarTarea $publicar): JsonResponse
    {
        $this->exigirAsignacion($request, $seccion->id, (int) $request->input('materia_id'));

        $datos = $request->validate([
            'materia_id' => ['required', 'integer'],
            'tipo' => ['required', Rule::enum(TipoTarea::class)],
            'titulo' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'fecha_entrega' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        $tarea = $publicar->ejecutar($datos + ['seccion_id' => $seccion->id], $request->user());

        return response()->json(['message' => 'Publicado. Se avisó a los apoderados del aula.', 'id' => $tarea->id], 201);
    }

    public function registrarConducta(Request $request, RegistrarConducta $registrar): JsonResponse
    {
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer', Rule::exists('alumnos', 'id')],
            'tipo' => ['required', Rule::enum(TipoConducta::class)],
            'descripcion' => ['required', 'string', 'max:500'],
        ]);

        // Solo sobre alumnos de alguna de sus aulas.
        $alumno = Alumno::query()->findOrFail($datos['alumno_id']);
        $esSuAlumno = $alumno->seccion_id !== null && Asignacion::query()
            ->where('personal_id', $this->docente($request)->id)->where('anio', now()->year)
            ->where('seccion_id', $alumno->seccion_id)->exists();

        abort_unless($esSuAlumno, 403, 'Ese alumno no pertenece a ninguna de tus aulas.');

        $registro = $registrar->ejecutar($datos + ['fecha' => today()->toDateString()], $request->user());

        return response()->json(['message' => 'Registrado. Se avisó al apoderado.', 'id' => $registro->id], 201);
    }

    /** Su horario semanal: los bloques en los que figura como docente. */
    public function horario(Request $request): JsonResponse
    {
        $dias = Horario::query()
            ->where('personal_id', $this->docente($request)->id)
            ->with(['materia', 'seccion.grado'])
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
                    'aula' => $bloque->seccion->nombreCompleto(),
                ])->values(),
            ])->values();

        return response()->json(['dias' => $dias]);
    }

    private function docente(Request $request): Personal
    {
        return $request->user()->personal ?? abort(403, 'Tu cuenta no está vinculada a un trabajador.');
    }

    /**
     * Corta si el docente no dicta esa materia en esa aula este año. Se llama
     * antes de validar el resto: quien no tiene el aula asignada recibe un 403
     * y no pistas sobre qué datos serían válidos.
     */
    private function exigirAsignacion(Request $request, int $seccionId, int $materiaId): void
    {
        $asignado = Asignacion::query()->where([
            'personal_id' => $this->docente($request)->id, 'seccion_id' => $seccionId,
            'materia_id' => $materiaId, 'anio' => now()->year,
        ])->exists();

        abort_unless($asignado, 403, 'No tienes asignada esa materia en esa aula.');
    }
}
