<?php

namespace App\Http\Resources;

use App\Models\Grado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Grado */
class GradoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nivel' => $this->nivel->value,
            'nombre' => $this->nombre,
            'orden' => $this->orden,
            'activo' => $this->activo,
            'secciones' => SeccionResource::collection($this->whenLoaded('secciones')),
        ];
    }
}
