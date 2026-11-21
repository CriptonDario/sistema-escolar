<?php

namespace Database\Factories;

use App\Enums\TipoPersonal;
use App\Models\Personal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Personal>
 */
class PersonalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('PER###??'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'cargo' => 'Docente',
            'tipo' => TipoPersonal::Docente,
            'activo' => true,
        ];
    }
}
