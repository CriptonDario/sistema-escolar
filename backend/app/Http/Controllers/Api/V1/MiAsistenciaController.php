<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AsistenciaResource;
use App\Http\Resources\PermisoResource;
use App\Models\Alumno;
use App\Support\Mes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La asistencia propia de un alumno o de un trabajador.
 */
class MiAsistenciaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $persona = $request->user()->alumno ?? $request->user()->personal;

        abort_if($persona === null, 404, 'Tu cuenta no está vinculada a un alumno ni a un trabajador.');

        $mes = Mes::desde($request->query('mes'));

        return response()->json([
            'mes' => $mes->clave(),
            'dias' => AsistenciaResource::collection(
                $persona->asistencias()->whereBetween('fecha', $mes->rango())->orderByDesc('fecha')->get()
            ),
            'permisos' => $persona instanceof Alumno
                ? PermisoResource::collection(
                    $persona->permisos()->vigentes()->whereBetween('fecha', $mes->rango())->orderByDesc('fecha')->get()
                )
                : [],
        ]);
    }
}
