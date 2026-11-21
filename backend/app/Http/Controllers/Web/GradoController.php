<?php

namespace App\Http\Controllers\Web;

use App\Actions\CrearGrado;
use App\Enums\Nivel;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarGradoRequest;
use App\Http\Requests\GuardarSeccionRequest;
use App\Models\Grado;
use App\Models\Seccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pantalla "Grados y secciones".
 */
class GradoController extends Controller
{
    public function index(): View
    {
        $grados = Grado::query()->ordenados()
            ->with(['secciones' => fn ($q) => $q->withCount('alumnos')])
            ->get();

        return view('grados.index', [
            'niveles' => Nivel::cases(),
            'porNivel' => $grados->groupBy(fn (Grado $grado) => $grado->nivel->value),
        ]);
    }

    public function store(GuardarGradoRequest $request, CrearGrado $crear): RedirectResponse
    {
        $crear->ejecutar($request->validated());

        return back()->with('ok', 'Grado creado.');
    }

    public function update(GuardarGradoRequest $request, Grado $grado): RedirectResponse
    {
        $grado->update($request->safe()->only(['nombre', 'orden', 'activo']));

        return back()->with('ok', 'Grado actualizado.');
    }

    public function destroy(Grado $grado): RedirectResponse
    {
        if ($grado->tieneAlumnos()) {
            return back()->with('error', 'El grado tiene alumnos: no se puede eliminar.');
        }

        DB::transaction(function () use ($grado) {
            $grado->secciones()->delete();
            $grado->delete();
        });

        return back()->with('ok', 'Grado eliminado.');
    }

    public function agregarSeccion(GuardarSeccionRequest $request, Grado $grado, CrearGrado $crear): RedirectResponse
    {
        $seccion = $crear->agregarSeccion($grado, $request->validated('nombre'));

        return $seccion
            ? back()->with('ok', "Sección {$seccion->nombre} agregada a {$grado->nombre}.")
            : back()->with('error', 'El grado ya usa todas las letras de la A a la Z.');
    }

    /** Activa o desactiva una sección. */
    public function alternarSeccion(Request $request, Seccion $seccion): RedirectResponse
    {
        $seccion->update(['activo' => ! $seccion->activo]);

        return back()->with('ok', $seccion->activo ? 'Sección reactivada.' : 'Sección desactivada.');
    }

    public function eliminarSeccion(Seccion $seccion): RedirectResponse
    {
        if ($seccion->alumnos()->exists()) {
            return back()->with('error', 'La sección tiene alumnos: desactívala en lugar de eliminarla.');
        }

        $seccion->delete();

        return back()->with('ok', 'Sección eliminada.');
    }
}
