<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Apoderado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Apoderado>
 */
class ApoderadoFactory extends Factory
{
    public function definition(): array
    {
        $telefono = fake()->unique()->numerify('9########');

        return [
            'user_id' => User::factory()->rol(Rol::Apoderado)->state(['usuario' => $telefono]),
            'nombres' => fake()->name(),
            'telefono' => $telefono,
        ];
    }
}
