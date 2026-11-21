<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** El registro de seguridad, para el administrador. */
class SeguridadController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate(['evento' => ['nullable', Rule::in(array_keys(Auditoria::EVENTOS))]]);

        return view('seguridad.index', [
            'filtros' => $filtros,
            'eventos' => Auditoria::EVENTOS,
            'registros' => Auditoria::query()
                ->when($filtros['evento'] ?? null, fn ($q, $evento) => $q->where('evento', $evento))
                ->with('user')->orderByDesc('id')->paginate(50)->withQueryString(),
            'fallosHoy' => Auditoria::query()->where('evento', 'login_fallido')->whereDate('created_at', today())->count(),
            'bloqueosHoy' => Auditoria::query()->where('evento', 'login_bloqueado')->whereDate('created_at', today())->count(),
            // Cuentas que siguen con la contraseña inicial: nadie las ha activado todavía.
            'sinActivar' => User::query()->where(['debe_cambiar_clave' => true, 'activo' => true])->count(),
            'conContrasenaInicialDebil' => config('colegio.clave_inicial') === '123456',
        ]);
    }
}
