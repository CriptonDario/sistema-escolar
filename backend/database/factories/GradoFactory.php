<?php

namespace Database\Factories;

use App\Enums\Nivel;
use App\Models\Grado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grado>
 */
class GradoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nivel' => Nivel::Primaria,
            'nombre' => fake()->unique()->numerify('Grado ###'),
            'orden' => 1,
            'activo' => true,
        ];
    }
}
