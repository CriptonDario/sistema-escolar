<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Cierra las sesiones abiertas de una cuenta: en la app (tokens) y en la web. */
class Sesiones
{
    /**
     * @param  string|null  $menosEsta  id de la sesión web que se conserva (la de quien hace el cambio sobre sí mismo)
     */
    public static function cerrarTodas(User $user, ?string $menosEsta = null): void
    {
        $user->tokens()->delete();
        self::cerrarWeb($user, $menosEsta);
    }

    public static function cerrarWeb(User $user, ?string $menosEsta = null): void
    {
        // Solo se pueden cerrar desde aquí cuando las sesiones están en la base de datos.
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($menosEsta, fn ($q) => $q->where('id', '!=', $menosEsta))
            ->delete();
    }
}
