<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'usuario' => fake()->unique()->userName(),
            'password' => 'Clave1234',
            'rol' => Rol::Admin,
            'debe_cambiar_clave' => false,
            'activo' => true,
        ];
    }

    public function rol(Rol $rol): static
    {
        return $this->state(['rol' => $rol]);
    }

    /** Cuenta recién creada por el colegio: aún con la contraseña inicial. */
    public function conClaveInicial(): static
    {
        return $this->state(fn () => [
            'password' => config('colegio.clave_inicial'),
            'debe_cambiar_clave' => true,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
