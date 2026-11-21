<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Matriculas\MatriculaEnLinea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisos de la pasarela de pagos (webhook). No lleva sesión: lo llama Mercado
 * Pago. El aviso solo trae un número de pago; que el pago sea real y esté
 * aprobado se comprueba consultándolo a Mercado Pago.
 */
class PagoEnLineaController extends Controller
{
    public function mercadoPago(Request $request, MatriculaEnLinea $matricula): JsonResponse
    {
        $esPago = in_array('payment', [$request->input('type'), $request->query('topic')], true);
        $id = (string) ($request->input('data.id') ?? $request->query('id') ?? '');

        if ($esPago && $id !== '') {
            try {
                $matricula->procesarPagoExterno($id);
            } catch (Throwable $e) {
                // Se responde 200 igual: Mercado Pago reintenta y el retorno del apoderado también lo procesa.
                Log::error('Aviso de Mercado Pago sin procesar', ['pago' => $id, 'motivo' => $e->getMessage()]);
            }
        }

        return response()->json(['recibido' => true]);
    }
}
