<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\Personal;
use App\Support\Aulas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HorarioController extends Controller
{
    public function index(Request $request): View
    {
        $aulas = Aulas::activas();
        $seccion = $aulas->firstWhere('id', (int) $request->query('seccion_id')) ?? $aulas->first();

        return view('horarios.index', [
            'aulas' => $aulas,
            'seccion' => $seccion,
            'dias' => Horario::DIAS,
            'porDia' => $seccion
                ? Horario::query()->where('seccion_id', $seccion->id)->with(['materia', 'personal'])
                    ->orderBy('hora_inicio')->get()->groupBy('dia')
                : collect(),
            'materias' => Materia::query()->where('activo', true)->orderBy('nombre')->get(),
            'docentes' => Personal::query()->activos()->orderBy('apellidos')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'seccion_id' => ['required', 'integer', Rule::exists('secciones', 'id')],
            'dia' => ['required', 'integer', 'between:1,6'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'materia_id' => ['nullable', 'integer', Rule::exists('materias', 'id')],
            'personal_id' => ['nullable', 'integer', Rule::exists('personal', 'id')],
            'titulo' => ['nullable', 'required_without:materia_id', 'string', 'max:60'],
        ], [
            'hora_fin.after' => 'La hora de fin debe ser posterior a la de inicio.',
            'titulo.required_without' => 'Elige una materia o escribe un título (por ejemplo, Recreo).',
        ]);

        // Dos bloques del mismo aula no pueden pisarse el mismo día.
        $choca = Horario::query()
            ->where('seccion_id', $datos['seccion_id'])->where('dia', $datos['dia'])
            ->where('hora_inicio', '<', $datos['hora_fin'].':00')
            ->where('hora_fin', '>', $datos['hora_inicio'].':00')
            ->exists();

        if ($choca) {
            return back()->withInput()->with('error', 'Ese horario se cruza con otro bloque del mismo día.');
        }

        Horario::query()->create($datos);

        return back()->with('ok', 'Bloque agregado al horario.');
    }

    public function destroy(Horario $horario): RedirectResponse
    {
        $horario->delete();

        return back()->with('ok', 'Bloque eliminado.');
    }
}
