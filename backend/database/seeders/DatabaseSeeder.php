<?php

namespace Database\Seeders;

use App\Enums\Nivel;
use App\Models\Grado;
use Illuminate\Database\Seeder;

/**
 * Datos con los que arranca un colegio nuevo: los grados habituales de cada
 * nivel, cada uno con su sección A. No crea ninguna cuenta: el primer
 * administrador se crea con `php artisan colegio:crear-admin`.
 */
class DatabaseSeeder extends Seeder
{
    private const GRADOS = [
        'inicial' => ['3 años', '4 años', '5 años'],
        'primaria' => ['1ro Primaria', '2do Primaria', '3ro Primaria', '4to Primaria', '5to Primaria', '6to Primaria'],
        'secundaria' => ['1ro Secundaria', '2do Secundaria', '3ro Secundaria', '4to Secundaria', '5to Secundaria'],
    ];

    public function run(): void
    {
        foreach (self::GRADOS as $nivel => $nombres) {
            foreach ($nombres as $posicion => $nombre) {
                $grado = Grado::query()->firstOrCreate(
                    ['nivel' => Nivel::from($nivel), 'nombre' => $nombre],
                    ['orden' => $posicion + 1],
                );

                $grado->secciones()->firstOrCreate(['nombre' => 'A']);
            }
        }
    }
}
