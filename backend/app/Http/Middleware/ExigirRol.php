<?php

namespace App\Http\Middleware;

use App\Enums\Rol;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo a las cuentas con alguno de los roles indicados.
 * Uso en rutas: ->middleware('rol:admin,guardia')
 */
class ExigirRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $permitidos = array_map(fn (string $rol) => Rol::from($rol), $roles);

        abort_unless($request->user()?->tieneRol(...$permitidos), 403, 'Tu cuenta no tiene acceso a esta función.');

        return $next($request);
    }
}
