<?php

namespace App\Actions;

use App\Models\Grado;
use App\Models\Seccion;
use Illuminate\Support\Facades\DB;

/**
 * Altas de la estructura académica: grados y sus secciones.
 */
class CrearGrado
{
    /**
     * Crea el grado con sus secciones (por defecto, solo la A).
     *
     * @param  array<string, mixed>  $datos  ya validados (GuardarGradoRequest)
     */
    public function ejecutar(array $datos): Grado
    {
        return DB::transaction(function () use ($datos) {
            $grado = Grado::query()->create([
                'nivel' => $datos['nivel'],
                'nombre' => $datos['nombre'],
                'orden' => $datos['orden'] ?? 0,
            ]);

            foreach ($datos['secciones'] ?? ['A'] as $nombre) {
                $grado->secciones()->create(['nombre' => $nombre]);
            }

            return $grado;
        });
    }

    /** Agrega al grado la sección indicada o, sin nombre, la siguiente letra libre. */
    public function agregarSeccion(Grado $grado, ?string $nombre = null): ?Seccion
    {
        $nombre = $nombre ?: $this->siguienteLetraLibre($grado);

        return $nombre === null ? null : $grado->secciones()->create(['nombre' => $nombre]);
    }

    /** Con A y B devuelve C; con A y C devuelve B. */
    private function siguienteLetraLibre(Grado $grado): ?string
    {
        $usadas = $grado->secciones()->pluck('nombre')->all();

        foreach (range('A', 'Z') as $letra) {
            if (! in_array($letra, $usadas, true)) {
                return $letra;
            }
        }

        return null;
    }
}
