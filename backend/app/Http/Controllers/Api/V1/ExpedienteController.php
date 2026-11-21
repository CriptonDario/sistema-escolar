<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Services\Academico\Expediente;
use App\Services\Pagos\EstadoDeCuenta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notas, horario, tareas, conducta y comunicados de un alumno.
 *
 * Las mismas rutas sirven a dos perfiles:
 *   - apoderado: /hijos/{alumno}/...  (solo de sus propios hijos)
 *   - alumno:    /mi/...              (solo los suyos)
 */
class ExpedienteController extends Controller
{
    public function __construct(private readonly Expediente $expediente) {}

    public function notas(Request $request, ?Alumno $alumno = null): JsonResponse
    {
        return response()->json($this->expediente->notas($this->alumno($request, $alumno), $this->anio($request)));
    }

    public function horario(Request $request, ?Alumno $alumno = null): JsonResponse
    {
        return response()->json(['dias' => $this->expediente->horario($this->alumno($request, $alumno))]);
    }

    public function tareas(Request $request, ?Alumno $alumno = null): JsonResponse
    {
        return response()->json(['tareas' => $this->expediente->tareas($this->alumno($request, $alumno))]);
    }

    public function conducta(Request $request, ?Alumno $alumno = null): JsonResponse
    {
        return response()->json(['registros' => $this->expediente->conducta($this->alumno($request, $alumno), $this->anio($request))]);
    }

    public function comunicados(Request $request, ?Alumno $alumno = null): JsonResponse
    {
        return response()->json(['comunicados' => $this->expediente->comunicados($this->alumno($request, $alumno))]);
    }

    /** Qué pagó y qué pensiones debe: solo lo ve el apoderado. */
    public function pagos(Request $request, Alumno $alumno, EstadoDeCuenta $estado): JsonResponse
    {
        return response()->json($estado->de($this->alumno($request, $alumno), $this->anio($request)));
    }

    /** El alumno consultado, después de comprobar que quien pregunta puede verlo. */
    private function alumno(Request $request, ?Alumno $alumno): Alumno
    {
        $user = $request->user();

        if ($user->rol === Rol::Alumno) {
            return $user->alumno ?? abort(404, 'Tu cuenta no está vinculada a un alumno.');
        }

        // Apoderado: solo sus hijos, aunque cambie el id en la petición.
        abort_if($alumno === null, 404);
        abort_unless((bool) $user->apoderado?->esResponsableDe($alumno->id), 403, 'No tienes acceso a ese alumno.');

        return $alumno;
    }

    private function anio(Request $request): int
    {
        $anio = (int) $request->query('anio', now()->year);

        return ($anio >= 2000 && $anio <= 2100) ? $anio : now()->year;
    }
}
