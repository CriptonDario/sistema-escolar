<?php

namespace App\Http\Resources;

use App\Models\Alumno;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Alumno */
class AlumnoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'dni' => $this->dni,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'foto' => $this->foto !== null ? route('api.alumnos.foto', $this->resource).'?v='.$this->updated_at?->timestamp : null,
            'activo' => $this->activo,
            'seccion_id' => $this->seccion_id,
            'aula' => $this->whenLoaded('seccion', fn () => $this->aula()),
            'nivel' => $this->whenLoaded('seccion', fn () => $this->seccion?->grado->nivel->value),
            'usuario' => $this->whenLoaded('user', fn () => $this->user?->usuario),
            'parentesco' => $this->whenPivotLoaded('alumno_apoderado', fn () => $this->resource->getRelation('pivot')->getAttribute('parentesco')),
            // Para el reverso del carné.
            'apoderados' => $this->whenLoaded('apoderados', fn () => $this->apoderados
                ->map(fn ($apoderado) => ['nombres' => $apoderado->nombres, 'telefono' => $apoderado->telefono])->values()),
            'hoy' => $this->whenLoaded('asistenciaDeHoy', fn () => $this->asistenciaDeHoy
                ? new AsistenciaResource($this->asistenciaDeHoy)
                : null),
        ];
    }
}
