<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\CrearGrado;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarSeccionRequest;
use App\Http\Resources\SeccionResource;
use App\Models\Grado;
use App\Models\Seccion;
use Illuminate\Http\JsonResponse;

class SeccionController extends Controller
{
    /** Agrega una sección al grado: la indicada o la siguiente letra libre. */
    public function store(GuardarSeccionRequest $request, Grado $grado, CrearGrado $crear): JsonResponse
    {
        $seccion = $crear->agregarSeccion($grado, $request->validated('nombre'));

        abort_if($seccion === null, 422, 'El grado ya usa todas las letras de la A a la Z.');

        return (new SeccionResource($seccion))->response()->setStatusCode(201);
    }

    public function update(GuardarSeccionRequest $request, Seccion $seccion): SeccionResource
    {
        $seccion->update($request->validated());

        return new SeccionResource($seccion);
    }

    /** Solo se elimina una sección sin alumnos; si los tiene, se desactiva. */
    public function destroy(Seccion $seccion): JsonResponse
    {
        abort_if($seccion->alumnos()->exists(), 409, 'La sección tiene alumnos. Desactívala en lugar de eliminarla.');

        $seccion->delete();

        return response()->json(['message' => 'Sección eliminada.']);
    }
}
