<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Services\Asistencia\RegistradorAsistencia;
use App\Services\Visitas\PasesDeVisita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla de la puerta: escanea el carné y registra entrada o salida.
 */
class KioscoController extends Controller
{
    public function index(): View
    {
        return view('kiosco.index');
    }

    /** La llama la pantalla por cada lectura, sin recargar la página. */
    public function marcar(Request $request, RegistradorAsistencia $registrador, PasesDeVisita $pases): JsonResponse
    {
        $datos = $request->validate(['codigo' => ['required', 'string', 'max:20']]);

        // Un pase de visitante no es asistencia: marca la salida de esa visita.
        if ($pases->esPase($datos['codigo'])) {
            return response()->json($pases->alEscanear($datos['codigo']));
        }

        $resultado = $registrador->marcar($datos['codigo'], $request->user());
        $persona = $resultado->persona;

        return response()->json([
            'evento' => $resultado->evento->value,
            'registrado' => $resultado->evento->registraCambio(),
            'mensaje' => $resultado->mensaje,
            'entrada' => Asistencia::horaCorta($resultado->asistencia->hora_entrada),
            'salida' => Asistencia::horaCorta($resultado->asistencia->hora_salida),
            'nombre' => $persona->nombreCompleto(),
            'detalle' => $persona instanceof Alumno ? $persona->aula() : $persona->cargo,
        ]);
    }
}
