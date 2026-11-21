<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\CrearGrado;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarGradoRequest;
use App\Http\Resources\GradoResource;
use App\Models\Grado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Estructura académica: los grados de cada nivel y sus secciones.
 */
class GradoController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return GradoResource::collection(
            Grado::query()->ordenados()->with(['secciones' => fn ($q) => $q->withCount('alumnos')])->get()
        );
    }

    public function store(GuardarGradoRequest $request, CrearGrado $crear): JsonResponse
    {
        $grado = $crear->ejecutar($request->validated());

        return (new GradoResource($grado->load('secciones')))->response()->setStatusCode(201);
    }

    public function update(GuardarGradoRequest $request, Grado $grado): GradoResource
    {
        $grado->update($request->safe()->only(['nombre', 'orden', 'activo']));

        return new GradoResource($grado->load('secciones'));
    }

    /** Solo se elimina un grado sin alumnos; si los tiene, se desactiva. */
    public function destroy(Grado $grado): JsonResponse
    {
        abort_if($grado->tieneAlumnos(), 409, 'El grado tiene alumnos. Desactívalo en lugar de eliminarlo.');

        DB::transaction(function () use ($grado) {
            $grado->secciones()->delete();
            $grado->delete();
        });

        return response()->json(['message' => 'Grado eliminado.']);
    }
}
