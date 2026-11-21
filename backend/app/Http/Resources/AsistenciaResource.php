<?php

namespace App\Http\Resources;

use App\Models\Asistencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Asistencia */
class AsistenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'fecha' => $this->fecha->toDateString(),
            'entrada' => Asistencia::horaCorta($this->hora_entrada),
            'salida' => Asistencia::horaCorta($this->hora_salida),
            'estado' => $this->estado->value,
        ];
    }
}
