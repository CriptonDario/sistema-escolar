<?php

namespace App\Http\Controllers\Web;

use App\Enums\TipoPersonal;
use App\Http\Controllers\Controller;
use App\Models\Asignacion;
use App\Models\Materia;
use App\Models\Personal;
use App\Support\Aulas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pantalla "Asignación de docentes": quién dicta qué materia en qué aula.
 */
class AsignacionController extends Controller
{
    public function index(Request $request): View
    {
        $anio = (int) $request->query('anio', now()->year);
        $anio = ($anio >= 2000 && $anio <= 2100) ? $anio : now()->year;

        return view('asignaciones.index', [
            'anio' => $anio,
            'aulas' => Aulas::activas(),
            'materias' => Materia::query()->where('activo', true)->orderBy('nombre')->get(),
            'docentes' => Personal::query()->activos()->where('tipo', TipoPersonal::Docente)->orderBy('apellidos')->orderBy('nombres')->get(),
            'porDocente' => Asignacion::query()->where('anio', $anio)
                ->with(['personal', 'seccion.grado', 'materia'])
                ->get()
                ->sortBy(fn (Asignacion $a) => $a->personal->apellidos.' '.$a->personal->nombres)
                ->groupBy('personal_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'personal_id' => ['required', 'integer', Rule::exists('personal', 'id')->where('activo', true)],
            'seccion_id' => ['required', 'integer', Rule::exists('secciones', 'id')],
            'materia_id' => [
                'required', 'integer', Rule::exists('materias', 'id'),
                Rule::unique('asignaciones', 'materia_id')->where('seccion_id', $request->input('seccion_id'))->where('anio', $request->input('anio')),
            ],
            'anio' => ['required', 'integer', 'between:2000,2100'],
        ], ['materia_id.unique' => 'Esa materia ya tiene docente en esa aula este año.']);

        Asignacion::query()->create($datos);

        return back()->with('ok', 'Docente asignado.');
    }

    public function destroy(Asignacion $asignacion): RedirectResponse
    {
        $asignacion->delete();

        return back()->with('ok', 'Asignación quitada.');
    }
}
