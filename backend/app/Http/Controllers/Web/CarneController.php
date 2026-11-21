<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Personal;
use App\Models\Seccion;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Impresión de carnés en lote: toda un aula, todos los alumnos o todo el personal. */
class CarneController extends Controller
{
    public function lote(Request $request): View
    {
        $filtros = $request->validate([
            'de' => ['nullable', 'in:alumnos,personal'],
            'seccion_id' => ['nullable', 'integer'],
        ]);
        $dePersonal = ($filtros['de'] ?? 'alumnos') === 'personal';
        $seccion = $dePersonal || empty($filtros['seccion_id']) ? null : Seccion::query()->with('grado')->findOrFail($filtros['seccion_id']);

        return view('carnes.lote', [
            'dePersonal' => $dePersonal,
            'titulo' => match (true) {
                $dePersonal => 'Carnés del personal',
                $seccion !== null => 'Carnés de '.$seccion->nombreCompleto(),
                default => 'Carnés de todos los alumnos',
            },
            'personas' => $dePersonal
                ? Personal::query()->where('activo', true)->orderBy('apellidos')->orderBy('nombres')->get()
                : Alumno::query()->activos()
                    ->when($seccion, fn ($q) => $q->where('seccion_id', $seccion->id))
                    ->with(['seccion.grado', 'apoderados'])
                    ->orderBy('apellidos')->orderBy('nombres')->get(),
        ]);
    }
}
