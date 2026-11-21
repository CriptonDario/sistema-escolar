<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\SolicitudMatricula;
use App\Services\Matriculas\MatriculaEnLinea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Matrícula en línea desde el portal de padres. */
class FamiliaMatriculaController extends Controller
{
    public function index(Request $request, MatriculaEnLinea $matricula): View
    {
        return view('familia.matricula', ['resumen' => $matricula->resumenPara($request->user()->apoderado)]);
    }

    public function solicitar(Request $request, Alumno $alumno, MatriculaEnLinea $matricula): RedirectResponse
    {
        $apoderado = $request->user()->apoderado;
        abort_unless($apoderado->esResponsableDe($alumno->id), 403, 'No tienes acceso a ese alumno.');

        $datos = $request->validate(['grado_id' => ['required', 'integer']], ['grado_id.required' => 'Elige el grado.']);
        $solicitud = $matricula->solicitar($apoderado, $alumno, (int) $datos['grado_id']);

        return back()->with('ok', $solicitud->pendiente()
            ? 'Solicitud registrada. La matrícula se confirma con el pago.'
            : "{$alumno->nombreCompleto()} quedó matriculado: el pago ya estaba registrado.");
    }

    public function pagar(Request $request, SolicitudMatricula $solicitud, MatriculaEnLinea $matricula): RedirectResponse
    {
        abort_unless($request->user()->apoderado->esResponsableDe($solicitud->alumno_id), 403, 'No tienes acceso a esa solicitud.');

        $url = $matricula->urlDePago($solicitud);

        return $url === null
            ? back()->withErrors(['matricula' => 'Por ahora no se puede pagar en línea. Puedes pagar en secretaría.'])
            : redirect()->away($url);
    }
}
