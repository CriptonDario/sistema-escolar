<?php

namespace App\Actions;

use App\Enums\TipoMensaje;
use App\Models\Conducta;
use App\Models\User;
use App\Services\Mensajeria\Mensajero;

/**
 * Registra un mérito, demérito, observación o citación y le avisa al apoderado.
 */
class RegistrarConducta
{
    public function __construct(private readonly Mensajero $mensajero) {}

    /**
     * @param  array<string, mixed>  $datos  alumno_id, tipo, descripcion, fecha
     */
    public function ejecutar(array $datos, User $registradoPor): Conducta
    {
        $registro = Conducta::query()->create($datos + ['registrado_por' => $registradoPor->id]);
        $registro->load('alumno');

        $this->mensajero->avisarApoderadosDe(
            $registro->alumno,
            TipoMensaje::Conducta,
            $registro->tipo->etiqueta().' de conducta',
            "{$registro->alumno->nombreCompleto()}: {$registro->descripcion}",
        );

        return $registro;
    }
}
