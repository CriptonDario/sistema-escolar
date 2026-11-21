<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dispositivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Registro del teléfono para recibir notificaciones push.
 */
class DispositivoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'plataforma' => ['required', Rule::in(['android', 'ios'])],
        ]);

        // Si ese teléfono estaba a nombre de otra cuenta, pasa a esta.
        Dispositivo::query()->updateOrCreate(
            ['token' => $datos['token']],
            ['user_id' => $request->user()->id, 'plataforma' => $datos['plataforma']],
        );

        return response()->json(['message' => 'Dispositivo registrado.']);
    }
}
