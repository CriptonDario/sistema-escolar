<?php

namespace Database\Factories;

use App\Models\Alumno;
use App\Models\Seccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alumno>
 */
class AlumnoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seccion_id' => Seccion::factory(),
            'codigo' => fake()->unique()->bothify('ALU###??'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'activo' => true,
        ];
    }
}
