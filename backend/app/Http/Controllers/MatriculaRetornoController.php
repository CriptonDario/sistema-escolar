<?php

namespace App\Http\Controllers;

use App\Models\SolicitudMatricula;
use App\Services\Matriculas\MatriculaEnLinea;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Adonde la pasarela devuelve al apoderado después de pagar. Es una página
 * pública (también se llega desde el navegador del celular, sin sesión web):
 * por eso no muestra datos del alumno y no confía en lo que trae la dirección,
 * solo toma el número de pago y lo consulta a la pasarela.
 */
class MatriculaRetornoController extends Controller
{
    public function __invoke(Request $request, MatriculaEnLinea $matricula): View
    {
        $idPago = (string) ($request->query('payment_id') ?? $request->query('collection_id') ?? '');
        $solicitud = $idPago === '' ? null : $matricula->procesarPagoExterno($idPago);

        return view('matricula.retorno', [
            'confirmada' => $solicitud?->estado === SolicitudMatricula::MATRICULADO,
        ]);
    }
}
