<?php

namespace App\Http\Controllers\Web;

use App\Actions\GestionarPermisos;
use App\Enums\TipoPermiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPermisoRequest;
use App\Models\Alumno;
use App\Models\Permiso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermisoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $desde = $filtros['desde'] ?? now()->startOfMonth()->toDateString();
        $hasta = $filtros['hasta'] ?? now()->endOfMonth()->toDateString();

        return view('permisos.index', [
            'permisos' => Permiso::query()
                ->with(['alumno.seccion.grado', 'alumno.apoderados'])
                ->whereBetween('fecha', [$desde, $hasta])
                ->orderByDesc('fecha')->orderByDesc('id')
                ->paginate(25)->withQueryString(),
            'alumnos' => Alumno::query()->activos()->orderBy('apellidos')->orderBy('nombres')->get(['id', 'codigo', 'nombres', 'apellidos']),
            'tipos' => TipoPermiso::cases(),
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
    }

    public function store(GuardarPermisoRequest $request, GestionarPermisos $permisos): RedirectResponse
    {
        [, $avisados] = $permisos->registrar($request->validated(), $request->user());

        return $avisados > 0
            ? back()->with('ok', 'Permiso registrado. Se avisó al apoderado en la app.')
            : back()->with('ok', 'Permiso registrado. El alumno no tiene apoderado con cuenta, así que nadie recibió el aviso.');
    }

    public function destroy(Permiso $permiso, GestionarPermisos $permisos): RedirectResponse
    {
        $permisos->anular($permiso);

        return back()->with('ok', 'Permiso anulado.');
    }
}
