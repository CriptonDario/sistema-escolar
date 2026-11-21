<?php

namespace App\Http\Middleware;

use App\Enums\Rol;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Condiciones para estar dentro del portal de padres: la cuenta es de un
 * apoderado, sigue activa y ya fue activada desde la app (creó su contraseña).
 * Se revisa en cada petición.
 */
class ExigirFamilia
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->activo || $user->rol !== Rol::Apoderado || $user->debe_cambiar_clave || $user->apoderado === null) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['usuario' => 'Tu cuenta no tiene acceso al portal de padres.']);
        }

        return $next($request);
    }
}
