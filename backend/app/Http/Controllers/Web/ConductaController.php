<?php

namespace App\Http\Controllers\Web;

use App\Actions\RegistrarConducta;
use App\Enums\TipoConducta;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Conducta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Conducta y tutoría: méritos, deméritos, observaciones y citaciones.
 * Cada registro le llega al apoderado en la app.
 */
class ConductaController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:60']]);

        return view('conducta.index', [
            'filtros' => $filtros,
            'tipos' => TipoConducta::cases(),
            'alumnos' => Alumno::query()->activos()->orderBy('apellidos')->orderBy('nombres')->get(['id', 'codigo', 'nombres', 'apellidos']),
            'registros' => Conducta::query()
                ->with('alumno.seccion.grado')
                ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->whereHas('alumno', fn ($a) => $a->buscar($texto)))
                ->orderByDesc('fecha')->orderByDesc('id')
                ->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request, RegistrarConducta $registrar): RedirectResponse
    {
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer', Rule::exists('alumnos', 'id')],
            'tipo' => ['required', Rule::enum(TipoConducta::class)],
            'descripcion' => ['required', 'string', 'max:500'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $registrar->ejecutar($datos, $request->user());

        return back()->with('ok', 'Registrado. Se avisó al apoderado.');
    }

    public function destroy(Conducta $conducta): RedirectResponse
    {
        $conducta->update(['anulado_en' => now()]);

        return back()->with('ok', 'Registro anulado.');
    }
}
