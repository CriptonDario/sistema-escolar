<?php

namespace App\Http\Resources;

use App\Models\Apoderado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Apoderado */
class ApoderadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombres' => $this->nombres,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'dni' => $this->dni,
            'activo' => $this->whenLoaded('user', fn () => (bool) $this->user?->activo),
            'debe_cambiar_clave' => $this->whenLoaded('user', fn () => (bool) $this->user?->debe_cambiar_clave),
            'hijos' => AlumnoResource::collection($this->whenLoaded('hijos')),
        ];
    }
}
