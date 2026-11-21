<?php

namespace App\Support;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Anota en la tabla `auditoria` lo que importa para la seguridad: ingresos,
 * intentos fallidos, cambios de contraseña, cuentas y configuración.
 *
 * Nunca guarda contraseñas ni tokens. Y nunca interrumpe lo que se estaba
 * haciendo: si no se puede anotar, se deja constancia en el log y se sigue.
 */
class RegistroDeSeguridad
{
    public static function anotar(string $evento, ?string $detalle = null, ?User $user = null): void
    {
        try {
            Auditoria::query()->create([
                'user_id' => ($user ?? auth()->user())?->id,
                'evento' => $evento,
                'detalle' => $detalle === null ? null : mb_substr($detalle, 0, 255),
                'ip' => request()->ip(),
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo anotar en el registro de seguridad', ['evento' => $evento, 'motivo' => $e->getMessage()]);
        }
    }
}
