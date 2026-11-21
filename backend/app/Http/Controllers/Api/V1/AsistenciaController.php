<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoAsistencia;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Personal;
use App\Services\Asistencia\RegistradorAsistencia;
use App\Services\Visitas\PasesDeVisita;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registro de asistencia desde la app o el kiosco. Solo admin y guardia.
 */
class AsistenciaController extends Controller
{
    /** Marca la entrada o la salida de quien presenta su carné. */
    public function marcar(Request $request, RegistradorAsistencia $registrador, PasesDeVisita $pases): JsonResponse
    {
        $datos = $request->validate(['codigo' => ['required', 'string', 'max:20']]);

        // Un pase de visitante no es asistencia: marca la salida de esa visita.
        if ($pases->esPase($datos['codigo'])) {
            $respuesta = $pases->alEscanear($datos['codigo']);

            return response()->json($respuesta + ['persona' => [
                'tipo' => 'visitante', 'codigo' => PasesDeVisita::normalizar($datos['codigo']),
                'nombre' => $respuesta['nombre'], 'detalle' => $respuesta['detalle'], 'foto' => null,
            ]]);
        }

        $resultado = $registrador->marcar($datos['codigo'], $request->user());

        return response()->json([
            'evento' => $resultado->evento->value,
            'registrado' => $resultado->evento->registraCambio(),
            'mensaje' => $resultado->mensaje,
            'entrada' => Asistencia::horaCorta($resultado->asistencia->hora_entrada),
            'salida' => Asistencia::horaCorta($resultado->asistencia->hora_salida),
            'persona' => $this->tarjeta($resultado->persona),
        ]);
    }

    /** Resumen del día y las últimas marcas. */
    public function hoy(): JsonResponse
    {
        $ultimas = Asistencia::query()
            ->whereDate('fecha', today())
            ->with(['persona' => fn (MorphTo $morph) => $morph->morphWith([Alumno::class => ['seccion.grado']])])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->map(function (Asistencia $asistencia) {
                $persona = $asistencia->persona;

                // Una marca cuya persona ya no existe no se lista.
                return ! ($persona instanceof Alumno || $persona instanceof Personal) ? null : [
                    'persona' => $this->tarjeta($persona),
                    'entrada' => Asistencia::horaCorta($asistencia->hora_entrada),
                    'salida' => Asistencia::horaCorta($asistencia->hora_salida),
                    'estado' => $asistencia->estado->value,
                ];
            })
            ->filter()->values();

        return response()->json([
            'fecha' => today()->toDateString(),
            'alumnos' => $this->resumen('alumno', Alumno::query()->activos()->count()),
            'personal' => $this->resumen('personal', Personal::query()->activos()->count()),
            'ultimas' => $ultimas,
        ]);
    }

    /**
     * Para marcar a mano a quien no trae su carné: se busca por nombre o
     * código y luego se envía ese código a "marcar".
     */
    public function personas(Request $request): JsonResponse
    {
        $datos = $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']]);

        $alumnos = Alumno::query()->activos()->buscar($datos['q'])
            ->with(['seccion.grado', 'asistenciaDeHoy'])
            ->orderBy('apellidos')->limit(20)->get();

        $personal = Personal::query()->activos()->buscar($datos['q'])
            ->with('asistenciaDeHoy')
            ->orderBy('apellidos')->limit(20)->get();

        return response()->json([
            'personas' => $alumnos->concat($personal)->map(fn (Alumno|Personal $persona) => $this->tarjeta($persona) + [
                'entrada' => Asistencia::horaCorta($persona->asistenciaDeHoy?->hora_entrada),
                'salida' => Asistencia::horaCorta($persona->asistenciaDeHoy?->hora_salida),
            ])->values(),
        ]);
    }

    /** Datos de una persona para mostrar en pantalla al marcar. */
    private function tarjeta(Alumno|Personal $persona): array
    {
        $esAlumno = $persona instanceof Alumno;

        return [
            'tipo' => $esAlumno ? 'alumno' : 'personal',
            'codigo' => $persona->codigo,
            'nombre' => $persona->nombreCompleto(),
            'detalle' => $esAlumno ? $persona->aula() : $persona->cargo,
            'foto' => $persona->foto,
        ];
    }

    private function resumen(string $tipoPersona, int $total): array
    {
        $delDia = Asistencia::query()->whereDate('fecha', today())->where('persona_type', $tipoPersona);

        return [
            'total' => $total,
            'presentes' => (clone $delDia)->count(),
            'tardanzas' => (clone $delDia)->where('estado', EstadoAsistencia::Tarde)->count(),
            'salieron' => (clone $delDia)->whereNotNull('hora_salida')->count(),
        ];
    }
}
