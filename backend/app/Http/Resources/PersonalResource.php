<?php

namespace App\Http\Resources;

use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Personal */
class PersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'dni' => $this->dni,
            'cargo' => $this->cargo,
            'tipo' => $this->tipo->value,
            'foto' => $this->foto !== null ? route('api.personal.foto', $this->resource).'?v='.$this->updated_at?->timestamp : null,
            'activo' => $this->activo,
            'usuario' => $this->whenLoaded('user', fn () => $this->user?->usuario),
        ];
    }
}
