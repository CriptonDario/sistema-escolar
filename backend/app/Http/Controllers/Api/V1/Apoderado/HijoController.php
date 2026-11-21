<?php

namespace App\Http\Controllers\Api\V1\Apoderado;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlumnoResource;
use App\Http\Resources\AsistenciaResource;
use App\Http\Resources\PermisoResource;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Support\Mes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HijoController extends Controller
{
    /** Los hijos del apoderado, con su aula y cómo va su día. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $hijos = $this->apoderado($request)->hijos()
            ->activos()
            ->with(['seccion.grado', 'asistenciaDeHoy', 'apoderados'])
            ->orderBy('apellidos')->orderBy('nombres')
            ->get();

        return AlumnoResource::collection($hijos);
    }

    public function asistencia(Request $request, Alumno $alumno): JsonResponse
    {
        // Un apoderado solo ve a sus hijos, aunque cambie el id en la petición.
        abort_unless($this->apoderado($request)->esResponsableDe($alumno->id), 403, 'No tienes acceso a ese alumno.');

        $mes = Mes::desde($request->query('mes'));

        return response()->json([
            'mes' => $mes->clave(),
            'dias' => AsistenciaResource::collection(
                $alumno->asistencias()->whereBetween('fecha', $mes->rango())->orderByDesc('fecha')->get()
            ),
            'permisos' => PermisoResource::collection(
                $alumno->permisos()->vigentes()->whereBetween('fecha', $mes->rango())->orderByDesc('fecha')->get()
            ),
        ]);
    }

    private function apoderado(Request $request): Apoderado
    {
        return $request->user()->apoderado ?? abort(403, 'Tu cuenta no está vinculada a un apoderado.');
    }
}
