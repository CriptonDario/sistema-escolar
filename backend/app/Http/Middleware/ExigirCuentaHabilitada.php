<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dos condiciones para usar el sistema una vez iniciada la sesión:
 *
 *   1. La cuenta sigue activa. Se revisa en cada petición, así desactivar a
 *      alguien lo deja fuera en el acto y no cuando venza su sesión.
 *   2. Ya reemplazó la contraseña inicial. Todas las cuentas de alumnos,
 *      personal y apoderados nacen con la misma clave conocida; quien entra
 *      con ella solo puede cambiarla, ver su perfil o salir.
 */
class ExigirCuentaHabilitada
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->activo) {
            // Una cuenta desactivada no conserva ninguna sesión de la app.
            $user->tokens()->delete();

            return response()->json(['message' => 'Tu cuenta ya no está activa.'], 401);
        }

        if ($user->debe_cambiar_clave) {
            return response()->json([
                'message' => 'Antes de continuar debes crear tu propia contraseña.',
                'codigo' => 'cambiar_clave',
            ], 403);
        }

        return $next($request);
    }
}
