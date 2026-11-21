<?php

namespace App\Http\Controllers\Web;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RegistroDeSeguridad;
use App\Support\Sesiones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Cuentas que operan el colegio desde el panel: administrador, guardia y dirección.
 */
class UsuarioController extends Controller
{
    private const ROLES = [Rol::Admin, Rol::Guardia, Rol::Direccion];

    public function index(): View
    {
        return view('usuarios.index', [
            'usuarios' => User::query()->whereIn('rol', self::ROLES)->orderByDesc('activo')->orderBy('usuario')->get(),
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'usuario' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('users', 'usuario')],
            'rol' => ['required', Rule::in(array_column(self::ROLES, 'value'))],
            'clave' => ['required', 'string', 'max:100', Password::min(8)->mixedCase()->numbers()],
        ], ['usuario.unique' => 'Ya existe una cuenta con ese usuario.']);

        User::query()->create([
            'nombre' => $datos['nombre'],
            'usuario' => mb_strtolower($datos['usuario']),
            'rol' => $datos['rol'],
            'password' => $datos['clave'],
        ]);

        RegistroDeSeguridad::anotar('usuario_creado', "{$datos['usuario']} ({$datos['rol']})");

        return back()->with('ok', "Cuenta {$datos['usuario']} creada.");
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        abort_unless($usuario->tieneRol(...self::ROLES), 404);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'rol' => ['required', Rule::in(array_column(self::ROLES, 'value'))],
            'clave' => ['nullable', 'string', 'max:100', Password::min(8)->mixedCase()->numbers()],
        ]);

        // Nadie se quita a sí mismo el rol de administrador: el colegio se
        // quedaría sin quien administre.
        if ($usuario->is($request->user()) && $datos['rol'] !== Rol::Admin->value) {
            return back()->with('error', 'No puedes quitarte a ti mismo el rol de administrador.');
        }

        $usuario->fill(['nombre' => $datos['nombre'], 'rol' => $datos['rol']]);

        if (! empty($datos['clave'])) {
            $usuario->password = $datos['clave'];
        }

        $usuario->save();

        // Con contraseña nueva, sus sesiones abiertas dejan de valer (menos la de quien se la cambia a sí mismo).
        if (! empty($datos['clave'])) {
            Sesiones::cerrarTodas($usuario, $usuario->is($request->user()) ? $request->session()->getId() : null);
        }

        RegistroDeSeguridad::anotar('usuario_editado', "{$usuario->usuario} ({$datos['rol']})".(empty($datos['clave']) ? '' : ', con contraseña nueva'));

        return back()->with('ok', 'Cuenta actualizada.');
    }

    /** Activa o desactiva una cuenta. */
    public function alternar(Request $request, User $usuario): RedirectResponse
    {
        abort_unless($usuario->tieneRol(...self::ROLES), 404);

        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $usuario->update(['activo' => ! $usuario->activo]);

        if (! $usuario->activo) {
            Sesiones::cerrarTodas($usuario);
        }

        RegistroDeSeguridad::anotar($usuario->activo ? 'usuario_activado' : 'usuario_desactivado', $usuario->usuario);

        return back()->with('ok', $usuario->activo ? 'Cuenta reactivada.' : 'Cuenta desactivada.');
    }
}
