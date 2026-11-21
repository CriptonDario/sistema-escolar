<?php

namespace App\Http\Controllers\Api\V1\Apoderado;

use App\Http\Controllers\Controller;
use App\Http\Resources\MensajeResource;
use App\Models\Apoderado;
use App\Models\Mensaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La bandeja de mensajes del apoderado.
 */
class MensajeController extends Controller
{
    /** Del más nuevo al más viejo. "antes_de" trae la página siguiente. */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'antes_de' => ['nullable', 'integer', 'min:1'],
            'limite' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $apoderado = $this->apoderado($request);

        $mensajes = $apoderado->mensajes()
            ->with('alumno')
            ->when($datos['antes_de'] ?? null, fn ($q, $id) => $q->where('id', '<', $id))
            ->orderByDesc('id')
            ->limit($datos['limite'] ?? 30)
            ->get();

        return response()->json([
            'no_leidos' => $apoderado->mensajes()->noLeidos()->count(),
            'mensajes' => MensajeResource::collection($mensajes),
        ]);
    }

    public function leer(Request $request, Mensaje $mensaje): JsonResponse
    {
        abort_unless($mensaje->apoderado_id === $this->apoderado($request)->id, 404);

        if ($mensaje->leido_en === null) {
            $mensaje->update(['leido_en' => now()]);
        }

        return response()->json(['message' => 'Mensaje marcado como leído.']);
    }

    public function leerTodos(Request $request): JsonResponse
    {
        $this->apoderado($request)->mensajes()->noLeidos()->update(['leido_en' => now()]);

        return response()->json(['message' => 'Todos los mensajes quedaron como leídos.']);
    }

    private function apoderado(Request $request): Apoderado
    {
        return $request->user()->apoderado ?? abort(403, 'Tu cuenta no está vinculada a un apoderado.');
    }
}
