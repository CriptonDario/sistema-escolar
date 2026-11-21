<?php

namespace App\Http\Resources;

use App\Models\Asistencia;
use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Permiso */
class PermisoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'tipo_etiqueta' => $this->tipo->etiqueta(),
            'fecha' => $this->fecha->toDateString(),
            'hora' => Asistencia::horaCorta($this->hora),
            'motivo' => $this->motivo,
            'vigente' => $this->estaVigente(),
            'alumno_id' => $this->alumno_id,
            'alumno' => $this->whenLoaded('alumno', fn () => $this->alumno->nombreCompleto()),
        ];
    }
}
