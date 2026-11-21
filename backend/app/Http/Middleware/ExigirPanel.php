<?php

namespace App\Http\Middleware;

use App\Enums\Rol;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Condiciones para estar dentro del panel web: la cuenta sigue activa y es de
 * las que operan el colegio. Se revisa en cada petición, así desactivar a
 * alguien lo saca en el acto.
 */
class ExigirPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->activo && $user->rol === Rol::Apoderado) {
            return redirect()->route('familia.inicio');
        }

        if (! $user->activo || ! $user->tieneRol(Rol::Admin, Rol::Guardia, Rol::Direccion)) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['usuario' => 'Tu cuenta ya no tiene acceso al panel.']);
        }

        return $next($request);
    }
}
