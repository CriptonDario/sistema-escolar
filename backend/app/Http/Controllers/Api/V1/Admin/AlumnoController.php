<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\GuardarAlumno;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarAlumnoRequest;
use App\Http\Resources\AlumnoResource;
use App\Models\Alumno;
use App\Services\Cuentas\CreadorDeCuentas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlumnoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'seccion_id' => ['nullable', 'integer'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $alumnos = Alumno::query()
            ->with(['seccion.grado', 'user'])
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->buscar($texto))
            ->when($filtros['seccion_id'] ?? null, fn ($q, $id) => $q->where('seccion_id', $id))
            ->when(isset($filtros['activo']), fn ($q) => $q->where('activo', (bool) $filtros['activo']))
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate(30);

        return AlumnoResource::collection($alumnos);
    }

    public function store(GuardarAlumnoRequest $request, GuardarAlumno $guardar): JsonResponse
    {
        $alumno = $guardar->ejecutar($request->validated());

        return (new AlumnoResource($alumno->load(['seccion.grado', 'user'])))->response()->setStatusCode(201);
    }

    public function show(Alumno $alumno): AlumnoResource
    {
        return new AlumnoResource($alumno->load(['seccion.grado', 'user']));
    }

    public function update(GuardarAlumnoRequest $request, Alumno $alumno, GuardarAlumno $guardar): AlumnoResource
    {
        return new AlumnoResource($guardar->ejecutar($request->validated(), $alumno)->load(['seccion.grado', 'user']));
    }

    /** No se borra: su historial de asistencia debe conservarse. Se da de baja. */
    public function destroy(Alumno $alumno, CreadorDeCuentas $cuentas): JsonResponse
    {
        $cuentas->darDeBaja($alumno);

        return response()->json(['message' => 'Alumno dado de baja.']);
    }
}
