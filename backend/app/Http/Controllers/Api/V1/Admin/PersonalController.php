<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\GuardarPersonal;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPersonalRequest;
use App\Http\Resources\PersonalResource;
use App\Models\Personal;
use App\Services\Cuentas\CreadorDeCuentas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $personal = Personal::query()
            ->with('user')
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->buscar($texto))
            ->when(isset($filtros['activo']), fn ($q) => $q->where('activo', (bool) $filtros['activo']))
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate(30);

        return PersonalResource::collection($personal);
    }

    public function store(GuardarPersonalRequest $request, GuardarPersonal $guardar): JsonResponse
    {
        $personal = $guardar->ejecutar($request->validated());

        return (new PersonalResource($personal->load('user')))->response()->setStatusCode(201);
    }

    public function show(Personal $personal): PersonalResource
    {
        return new PersonalResource($personal->load('user'));
    }

    public function update(GuardarPersonalRequest $request, Personal $personal, GuardarPersonal $guardar): PersonalResource
    {
        return new PersonalResource($guardar->ejecutar($request->validated(), $personal)->load('user'));
    }

    /** No se borra: su historial de asistencia debe conservarse. Se da de baja. */
    public function destroy(Personal $personal, CreadorDeCuentas $cuentas): JsonResponse
    {
        $cuentas->darDeBaja($personal);

        return response()->json(['message' => 'Trabajador dado de baja.']);
    }
}
