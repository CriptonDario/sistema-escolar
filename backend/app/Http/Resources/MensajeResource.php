<?php

namespace App\Http\Resources;

use App\Models\Mensaje;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Mensaje */
class MensajeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'titulo' => $this->titulo,
            'cuerpo' => $this->cuerpo,
            'fecha' => $this->created_at->toIso8601String(),
            'leido' => $this->leido_en !== null,
            'alumno_id' => $this->alumno_id,
            'alumno' => $this->whenLoaded('alumno', fn () => $this->alumno?->nombreCompleto()),
        ];
    }
}
