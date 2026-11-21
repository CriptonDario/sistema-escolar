<?php

namespace App\Http\Resources;

use App\Models\Seccion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Seccion */
class SeccionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grado_id' => $this->grado_id,
            'nombre' => $this->nombre,
            'activo' => $this->activo,
            'alumnos' => $this->whenCounted('alumnos'),
        ];
    }
}
