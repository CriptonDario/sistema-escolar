<?php

namespace Database\Factories;

use App\Models\Grado;
use App\Models\Seccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seccion>
 */
class SeccionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'grado_id' => Grado::factory(),
            'nombre' => 'A',
            'activo' => true,
        ];
    }
}
