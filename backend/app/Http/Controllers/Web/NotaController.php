<?php

namespace App\Http\Controllers\Web;

use App\Actions\GuardarNotas;
use App\Enums\EscalaCalificacion;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Materia;
use App\Models\Nota;
use App\Models\Seccion;
use App\Support\Aulas;
use App\Support\Escalas;
use App\Support\Periodos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Registro de notas: se elige aula, materia y periodo, y se llena la lista.
 */
class NotaController extends Controller
{
    public function index(Request $request, Periodos $periodos, Escalas $escalas): View
    {
        $filtros = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'seccion_id' => ['nullable', 'integer'],
            'materia_id' => ['nullable', 'integer'],
            'periodo' => ['nullable', 'integer', 'between:1,4'],
        ]);

        $anio = (int) ($filtros['anio'] ?? now()->year);
        $periodo = min((int) ($filtros['periodo'] ?? 1), $periodos->cantidad());
        $seccion = isset($filtros['seccion_id']) ? Seccion::query()->with('grado')->find($filtros['seccion_id']) : null;
        $materia = isset($filtros['materia_id']) ? Materia::query()->find($filtros['materia_id']) : null;

        $alumnos = collect();
        $notas = collect();

        if ($seccion && $materia) {
            $alumnos = Alumno::query()->activos()->where('seccion_id', $seccion->id)->orderBy('apellidos')->orderBy('nombres')->get();
            $notas = Nota::query()
                ->whereIn('alumno_id', $alumnos->modelKeys())
                ->where(['materia_id' => $materia->id, 'anio' => $anio, 'periodo' => $periodo])
                ->get()->keyBy('alumno_id');
        }

        return view('notas.index', [
            'anio' => $anio,
            'periodo' => $periodo,
            'periodos' => $periodos->todos(),
            'aulas' => Aulas::activas(),
            'materias' => $seccion
                ? Materia::query()->where('activo', true)->delNivel($seccion->grado->nivel)->orderBy('nombre')->get()
                : Materia::query()->where('activo', true)->orderBy('nombre')->get(),
            'seccion' => $seccion,
            'materia' => $materia,
            'alumnos' => $alumnos,
            'notas' => $notas,
            // El nivel del aula decide si se califica con números o con letras.
            'escala' => $seccion ? $escalas->del($seccion->grado->nivel) : null,
            'letras' => EscalaCalificacion::LETRAS,
            'notaMaxima' => EscalaCalificacion::NOTA_MAXIMA,
        ]);
    }

    /** Guarda la lista completa: una nota vacía borra la que hubiera. */
    public function guardar(Request $request, Periodos $periodos, GuardarNotas $guardar): RedirectResponse
    {
        $seccion = Seccion::query()->with('grado')->findOrFail((int) $request->input('seccion_id'));

        $datos = $request->validate([
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'seccion_id' => ['required', 'integer'],
            'materia_id' => ['required', 'integer', Rule::exists('materias', 'id')],
            'periodo' => ['required', 'integer', 'between:1,'.$periodos->cantidad()],
            'notas' => ['required', 'array'],
            'notas.*' => $guardar->escalaDe($seccion)->reglas(),
        ], [
            'notas.*.between' => 'Las notas van de 0 a '.EscalaCalificacion::NOTA_MAXIMA.'.',
            'notas.*.in' => 'La calificación debe ser AD, A, B o C.',
        ]);

        $guardar->ejecutar($seccion, (int) $datos['materia_id'], (int) $datos['anio'], (int) $datos['periodo'], $datos['notas'], $request->user());

        return back()->with('ok', 'Notas guardadas.');
    }
}
