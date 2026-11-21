<?php

namespace App\Http\Controllers\Web;

use App\Actions\GuardarApoderado;
use App\Enums\Parentesco;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarApoderadoRequest;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Services\Cuentas\CreadorDeCuentas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pantalla "Apoderados": cuentas de la app y qué hijos ve cada una.
 */
class ApoderadoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:60']]);

        return view('apoderados.index', [
            'apoderados' => Apoderado::query()
                ->with(['user', 'hijos'])
                ->withCount('dispositivos')
                ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->buscar($texto))
                ->orderBy('nombres')
                ->paginate(25)->withQueryString(),
            'alumnos' => Alumno::query()->activos()->orderBy('apellidos')->orderBy('nombres')->get(['id', 'codigo', 'nombres', 'apellidos']),
            'parentescos' => Parentesco::cases(),
            'sinApoderado' => Alumno::query()->activos()->whereDoesntHave('apoderados')->count(),
            'filtros' => $filtros,
        ]);
    }

    public function store(GuardarApoderadoRequest $request, GuardarApoderado $guardar): RedirectResponse
    {
        $apoderado = $guardar->ejecutar($request->validated());

        return back()->with('ok', "Cuenta creada para {$apoderado->nombres}. Entra a la app con su teléfono.");
    }

    public function update(GuardarApoderadoRequest $request, Apoderado $apoderado, GuardarApoderado $guardar): RedirectResponse
    {
        $guardar->ejecutar($request->validated(), $apoderado);

        return back()->with('ok', 'Datos actualizados.');
    }

    /** Activa o desactiva la cuenta del apoderado. */
    public function alternar(Apoderado $apoderado): RedirectResponse
    {
        $user = $apoderado->user;

        if ($user === null) {
            return back()->with('error', 'El apoderado no tiene cuenta.');
        }

        $user->update(['activo' => ! $user->activo]);

        if (! $user->activo) {
            $user->tokens()->delete();
        }

        return back()->with('ok', $user->activo ? 'Cuenta reactivada.' : 'Cuenta desactivada.');
    }

    public function vincular(Request $request, Apoderado $apoderado, GuardarApoderado $guardar): RedirectResponse
    {
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer', Rule::exists('alumnos', 'id')],
            'parentesco' => ['nullable', Rule::enum(Parentesco::class)],
        ]);

        $guardar->vincular($apoderado, $datos['alumno_id'], $datos['parentesco'] ?? null);

        return back()->with('ok', 'Alumno vinculado.');
    }

    public function desvincular(Apoderado $apoderado, Alumno $alumno): RedirectResponse
    {
        $apoderado->hijos()->detach($alumno->id);

        return back()->with('ok', 'Alumno desvinculado.');
    }

    public function restablecerClave(Apoderado $apoderado, CreadorDeCuentas $cuentas): RedirectResponse
    {
        if ($apoderado->user === null) {
            return back()->with('error', 'El apoderado no tiene cuenta.');
        }

        $cuentas->restablecerClave($apoderado->user);

        return back()->with('ok', 'Contraseña restablecida a la inicial. Se cerraron sus sesiones en la app.');
    }
}
