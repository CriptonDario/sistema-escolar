<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Services\Academico\Expediente;
use App\Services\Pagos\EstadoDeCuenta;
use App\Support\Mes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Portal web de padres y apoderados. Muestra lo mismo que la app: sus
 * mensajes y la información de sus propios hijos, nunca la de otros.
 */
class FamiliaController extends Controller
{
    private const SECCIONES = [
        'asistencia' => 'Asistencia', 'carne' => 'Carné', 'notas' => 'Notas', 'tareas' => 'Tareas', 'horario' => 'Horario',
        'conducta' => 'Conducta', 'pagos' => 'Pagos', 'comunicados' => 'Comunicados',
    ];

    public function inicio(Request $request): View
    {
        $apoderado = $this->apoderado($request);

        return view('familia.inicio', [
            'apoderado' => $apoderado,
            'hijos' => $apoderado->hijos()->activos()->with(['seccion.grado', 'asistenciaDeHoy'])->orderBy('apellidos')->get(),
            'mensajes' => $apoderado->mensajes()->with('alumno')->orderByDesc('id')->paginate(20),
            'noLeidos' => $apoderado->mensajes()->noLeidos()->count(),
        ]);
    }

    public function leerMensajes(Request $request): RedirectResponse
    {
        $this->apoderado($request)->mensajes()->noLeidos()->update(['leido_en' => now()]);

        return back()->with('ok', 'Mensajes marcados como leídos.');
    }

    public function hijo(Request $request, Alumno $alumno, Expediente $expediente, EstadoDeCuenta $estado): View
    {
        // Un apoderado solo ve a sus hijos, aunque cambie el número en la dirección.
        abort_unless($this->apoderado($request)->esResponsableDe($alumno->id), 403, 'No tienes acceso a ese alumno.');

        $ver = array_key_exists((string) $request->query('ver'), self::SECCIONES) ? (string) $request->query('ver') : 'asistencia';
        $mes = Mes::desde($request->query('mes'));
        $anio = now()->year;

        // Solo se consulta la sección que se está mirando.
        $datos = match ($ver) {
            'asistencia' => [
                'mes' => $mes->clave(),
                'dias' => $alumno->asistencias()->whereBetween('fecha', $mes->rango())->orderByDesc('fecha')->get(),
                'permisos' => $alumno->permisos()->vigentes()->whereBetween('fecha', $mes->rango())->orderByDesc('fecha')->get(),
            ],
            'carne' => ['alumno' => $alumno->loadMissing(['seccion.grado', 'apoderados'])],
            'notas' => $expediente->notas($alumno, $anio),
            'tareas' => ['tareas' => $expediente->tareas($alumno)],
            'horario' => ['dias' => $expediente->horario($alumno)],
            'conducta' => ['registros' => $expediente->conducta($alumno, $anio)],
            'pagos' => $estado->de($alumno, $anio),
            'comunicados' => ['comunicados' => $expediente->comunicados($alumno)],
        };

        return view('familia.hijo', [
            'alumno' => $alumno->load('seccion.grado'),
            'secciones' => self::SECCIONES,
            'ver' => $ver,
            'datos' => $datos,
        ]);
    }

    private function apoderado(Request $request): Apoderado
    {
        return $request->user()->apoderado;
    }
}
