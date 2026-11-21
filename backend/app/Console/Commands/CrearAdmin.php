<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Crea una cuenta de administrador, guardia o dirección.
 *
 * El sistema se instala sin ninguna cuenta: una contraseña que viniera
 * escrita en el código sería una contraseña que cualquiera con el código
 * conoce. Acá la elige quien instala y no queda guardada en ningún archivo.
 */
class CrearAdmin extends Command
{
    protected $signature = 'colegio:crear-admin {usuario}
        {--rol=admin : admin, guardia o direccion}
        {--nombre= : Nombre a mostrar}
        {--clave= : Contraseña; si se omite, se pide sin mostrarla en pantalla}';

    protected $description = 'Crea una cuenta de administrador, guardia o dirección';

    public function handle(): int
    {
        $rol = Rol::tryFrom((string) $this->option('rol'));

        if (! in_array($rol, [Rol::Admin, Rol::Guardia, Rol::Direccion], true)) {
            $this->error('El rol debe ser admin, guardia o direccion.');

            return self::FAILURE;
        }

        $usuario = mb_strtolower(trim((string) $this->argument('usuario')));
        $clave = (string) ($this->option('clave')
            ?? $this->secret('Contraseña (mínimo 8 caracteres, con mayúsculas, minúsculas y números)'));

        $validacion = Validator::make(
            ['usuario' => $usuario, 'clave' => $clave],
            [
                'usuario' => ['required', 'string', 'max:120', 'unique:users,usuario'],
                'clave' => ['required', Password::min(8)->mixedCase()->numbers()],
            ],
            ['usuario.unique' => 'Ya existe una cuenta con ese usuario.'],
        );

        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'nombre' => $this->option('nombre') ?: $usuario,
            'usuario' => $usuario,
            'password' => $clave,
            'rol' => $rol,
        ]);

        $this->info("Cuenta creada: {$usuario} ({$rol->value}).");

        return self::SUCCESS;
    }
}
