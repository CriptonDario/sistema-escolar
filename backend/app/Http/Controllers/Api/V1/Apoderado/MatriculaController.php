<?php

namespace App\Http\Controllers\Api\V1\Apoderado;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\SolicitudMatricula;
use App\Services\Matriculas\MatriculaEnLinea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Matrícula en línea desde la app. */
class MatriculaController extends Controller
{
    public function index(Request $request, MatriculaEnLinea $matricula): JsonResponse
    {
        return response()->json($matricula->resumenPara($this->apoderado($request)));
    }

    public function solicitar(Request $request, Alumno $alumno, MatriculaEnLinea $matricula): JsonResponse
    {
        $apoderado = $this->apoderado($request);
        abort_unless($apoderado->esResponsableDe($alumno->id), 403, 'No tienes acceso a ese alumno.');

        $datos = $request->validate(['grado_id' => ['required', 'integer']]);
        $solicitud = $matricula->solicitar($apoderado, $alumno, (int) $datos['grado_id']);

        return response()->json([
            'message' => $solicitud->pendiente()
                ? 'Solicitud registrada. La matrícula se confirma con el pago.'
                : "{$alumno->nombreCompleto()} quedó matriculado: el pago ya estaba registrado.",
        ] + $matricula->resumenPara($apoderado));
    }

    /** Devuelve la dirección de la pasarela; la app la abre en el navegador. */
    public function pagar(Request $request, SolicitudMatricula $solicitud, MatriculaEnLinea $matricula): JsonResponse
    {
        abort_unless($this->apoderado($request)->esResponsableDe($solicitud->alumno_id), 403, 'No tienes acceso a esa solicitud.');

        $url = $matricula->urlDePago($solicitud);

        return $url === null
            ? response()->json(['message' => 'Por ahora no se puede pagar en línea. Puedes pagar en secretaría.'], 422)
            : response()->json(['url' => $url]);
    }

    private function apoderado(Request $request): Apoderado
    {
        return $request->user()->apoderado ?? abort(403, 'Tu cuenta no está vinculada a un apoderado.');
    }
}
