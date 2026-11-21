<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\GuardarApoderado;
use App\Enums\Parentesco;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarApoderadoRequest;
use App\Http\Resources\ApoderadoResource;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Services\Cuentas\CreadorDeCuentas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Cuentas de padres, madres y apoderados, y qué hijos ve cada una.
 */
class ApoderadoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:60']]);

        $apoderados = Apoderado::query()
            ->with(['user', 'hijos'])
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->buscar($texto))
            ->orderBy('nombres')
            ->paginate(30);

        return ApoderadoResource::collection($apoderados);
    }

    /** Crea la cuenta (usuario = teléfono) y vincula a sus hijos. */
    public function store(GuardarApoderadoRequest $request, GuardarApoderado $guardar): JsonResponse
    {
        $apoderado = $guardar->ejecutar($request->validated());

        return (new ApoderadoResource($apoderado->load(['user', 'hijos'])))->response()->setStatusCode(201);
    }

    public function show(Apoderado $apoderado): ApoderadoResource
    {
        return new ApoderadoResource($apoderado->load(['user', 'hijos']));
    }

    public function update(GuardarApoderadoRequest $request, Apoderado $apoderado, GuardarApoderado $guardar): ApoderadoResource
    {
        return new ApoderadoResource($guardar->ejecutar($request->validated(), $apoderado)->load(['user', 'hijos']));
    }

    /** Desactiva la cuenta: deja de entrar a la app y de recibir mensajes. */
    public function destroy(Apoderado $apoderado): JsonResponse
    {
        $apoderado->user?->update(['activo' => false]);
        $apoderado->user?->tokens()->delete();

        return response()->json(['message' => 'Cuenta desactivada.']);
    }

    public function vincular(Request $request, Apoderado $apoderado, GuardarApoderado $guardar): ApoderadoResource
    {
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer', Rule::exists('alumnos', 'id')],
            'parentesco' => ['nullable', Rule::enum(Parentesco::class)],
        ]);

        $guardar->vincular($apoderado, $datos['alumno_id'], $datos['parentesco'] ?? null);

        return new ApoderadoResource($apoderado->load(['user', 'hijos']));
    }

    public function desvincular(Apoderado $apoderado, Alumno $alumno): ApoderadoResource
    {
        $apoderado->hijos()->detach($alumno->id);

        return new ApoderadoResource($apoderado->load(['user', 'hijos']));
    }

    /** Olvidó su contraseña: vuelve a la inicial y deberá cambiarla. */
    public function restablecerClave(Apoderado $apoderado, CreadorDeCuentas $cuentas): JsonResponse
    {
        abort_if($apoderado->user === null, 404, 'El apoderado no tiene cuenta.');

        $cuentas->restablecerClave($apoderado->user);

        return response()->json(['message' => 'Contraseña restablecida. Se cerraron sus sesiones.']);
    }
}
