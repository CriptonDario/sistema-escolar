<?php

namespace App\Services\Cuentas;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\Personal;
use App\Models\User;
use App\Support\RegistroDeSeguridad;
use App\Support\Sesiones;
use Illuminate\Validation\ValidationException;

/**
 * Crea la cuenta de acceso de un alumno, un trabajador o un apoderado.
 *
 * Todas nacen con la contraseña inicial del colegio y marcadas para cambiarla
 * en el primer ingreso.
 */
class CreadorDeCuentas
{
    public function crear(string $nombre, string $usuario, Rol $rol): User
    {
        $usuario = mb_strtolower(trim($usuario));

        if (User::query()->where('usuario', $usuario)->exists()) {
            throw ValidationException::withMessages([
                'usuario' => "Ya existe una cuenta con el usuario {$usuario}.",
            ]);
        }

        return User::query()->create([
            'nombre' => $nombre,
            'usuario' => $usuario,
            'password' => config('colegio.clave_inicial'),
            'rol' => $rol,
            'debe_cambiar_clave' => true,
            'activo' => true,
        ]);
    }

    /**
     * Deja a la persona con una cuenta cuyo usuario es el correo dado: la crea
     * si no tenía, o le actualiza el usuario y el nombre si ya existía.
     */
    public function sincronizar(Alumno|Personal $persona, string $correo, Rol $rol): User
    {
        if ($persona->user) {
            $persona->user->update(['usuario' => mb_strtolower(trim($correo)), 'nombre' => $persona->nombreCompleto()]);

            return $persona->user;
        }

        $user = $this->crear($persona->nombreCompleto(), $correo, $rol);

        $persona->update(['user_id' => $user->id]);
        $persona->setRelation('user', $user);

        return $user;
    }

    /** Olvidó su contraseña: vuelve a la inicial y se cierran sus sesiones. */
    public function restablecerClave(User $user): void
    {
        $user->forceFill([
            'password' => config('colegio.clave_inicial'),
            'debe_cambiar_clave' => true,
        ])->save();

        Sesiones::cerrarTodas($user);
        RegistroDeSeguridad::anotar('clave_restablecida', $user->usuario);
    }

    /** Da de baja a una persona: conserva su historial y le cierra el acceso. */
    public function darDeBaja(Alumno|Personal $persona): void
    {
        $persona->update(['activo' => false]);
        $persona->user?->update(['activo' => false]);
        if ($persona->user) {
            Sesiones::cerrarTodas($persona->user);
        }
    }
}
