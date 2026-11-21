<?php

namespace App\Support;

use App\Models\Seccion;
use Illuminate\Database\Eloquent\Collection;

/**
 * Las aulas (grado + sección) activas, en el orden en que se muestran en los
 * desplegables: inicial, primaria, secundaria; y dentro, por grado y sección.
 */
class Aulas
{
    /** @return Collection<int, Seccion> */
    public static function activas(): Collection
    {
        return Seccion::query()
            ->where('secciones.activo', true)
            ->join('grados', 'grados.id', '=', 'secciones.grado_id')
            ->where('grados.activo', true)
            ->orderByRaw("CASE grados.nivel WHEN 'inicial' THEN 1 WHEN 'primaria' THEN 2 ELSE 3 END")
            ->orderBy('grados.orden')->orderBy('grados.nombre')->orderBy('secciones.nombre')
            ->select('secciones.*')
            ->with('grado')
            ->get();
    }
}
