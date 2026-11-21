<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\GestionarPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPermisoRequest;
use App\Http\Resources\PermisoResource;
use App\Models\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Permisos de alumnos. Registrar o anular uno le avisa al apoderado en la app.
 */
class PermisoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'alumno_id' => ['nullable', 'integer'],
        ]);

        $permisos = Permiso::query()
            ->with('alumno')
            ->whereBetween('fecha', [
                $filtros['desde'] ?? now()->startOfMonth()->toDateString(),
                $filtros['hasta'] ?? now()->endOfMonth()->toDateString(),
            ])
            ->when($filtros['alumno_id'] ?? null, fn ($q, $id) => $q->where('alumno_id', $id))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(30);

        return PermisoResource::collection($permisos);
    }

    public function store(GuardarPermisoRequest $request, GestionarPermisos $permisos): JsonResponse
    {
        [$permiso, $avisados] = $permisos->registrar($request->validated(), $request->user());

        return (new PermisoResource($permiso))
            ->additional(['apoderados_avisados' => $avisados])
            ->response()->setStatusCode(201);
    }

    /** Anula el permiso (no se borra) y avisa al apoderado. */
    public function destroy(Permiso $permiso, GestionarPermisos $permisos): JsonResponse
    {
        $permisos->anular($permiso);

        return response()->json(['message' => 'Permiso anulado.']);
    }
}
