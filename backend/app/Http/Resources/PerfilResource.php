<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lo que la app necesita saber de la cuenta que inició sesión.
 *
 * @mixin User
 */
class PerfilResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'usuario' => $this->usuario,
            'rol' => $this->rol->value,
            'debe_cambiar_clave' => $this->debe_cambiar_clave,
            // Código del carné (alumnos y personal): la app lo muestra como QR.
            'codigo' => $this->alumno->codigo ?? $this->personal?->codigo,
            // Lo que va impreso en su carné, con el mismo modelo que el del panel.
            'carne' => $this->carne(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function carne(): ?array
    {
        if ($alumno = $this->alumno) {
            $alumno->loadMissing(['seccion.grado', 'apoderados']);

            return [
                'titulo' => 'CARNÉ DE ALUMNO',
                'nombre' => $alumno->apellidos.' '.$alumno->nombres,
                'iniciales' => mb_strtoupper(mb_substr($alumno->nombres, 0, 1).mb_substr($alumno->apellidos, 0, 1)),
                'detalle_etiqueta' => 'Grado y sección',
                'detalle' => $alumno->aula(),
                'dni' => $alumno->dni,
                'foto' => $alumno->foto !== null ? route('api.alumnos.foto', $alumno).'?v='.$alumno->updated_at?->timestamp : null,
                'apoderados' => $alumno->apoderados->pluck('nombres')->join(' - '),
                'telefono' => $alumno->apoderados->first(fn ($apoderado) => filled($apoderado->telefono))?->telefono,
            ];
        }

        if ($personal = $this->personal) {
            return [
                'titulo' => $personal->tituloDelCarne(),
                'nombre' => $personal->apellidos.' '.$personal->nombres,
                'iniciales' => mb_strtoupper(mb_substr($personal->nombres, 0, 1).mb_substr($personal->apellidos, 0, 1)),
                'detalle_etiqueta' => 'Cargo',
                'detalle' => $personal->cargo ?: $personal->tipo->etiqueta(),
                'dni' => $personal->dni,
                'foto' => $personal->foto !== null ? route('api.personal.foto', $personal).'?v='.$personal->updated_at?->timestamp : null,
            ];
        }

        return null;
    }
}
