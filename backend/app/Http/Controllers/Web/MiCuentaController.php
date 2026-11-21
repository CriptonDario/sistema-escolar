<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\RegistroDeSeguridad;
use App\Support\Sesiones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class MiCuentaController extends Controller
{
    /** Cambia la contraseña de quien tiene la sesión abierta. */
    public function cambiarClave(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'clave_actual' => ['required', 'string', 'current_password'],
            'clave_nueva' => ['required', 'string', 'max:100', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ], [
            'clave_actual.current_password' => 'La contraseña actual no es correcta.',
            'clave_nueva.confirmed' => 'Las contraseñas nuevas no coinciden.',
        ]);

        $request->user()->forceFill(['password' => $datos['clave_nueva']])->save();

        // Cierra las sesiones abiertas en otros navegadores.
        Auth::logoutOtherDevices($datos['clave_nueva']);
        Sesiones::cerrarTodas($request->user(), $request->session()->getId());
        RegistroDeSeguridad::anotar('clave_cambiada', 'Desde el panel');

        return back()->with('ok', 'Tu contraseña fue actualizada.');
    }
}
