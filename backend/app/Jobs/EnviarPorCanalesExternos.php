<?php

namespace App\Jobs;

use App\Enums\TipoMensaje;
use App\Models\Mensaje;
use App\Services\Canales\Sofia;
use App\Services\Canales\WhatsApp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Además de la app, manda un mensaje por los canales externos que el colegio
 * tenga activos: WhatsApp directo y Sofía.
 *
 * Va por la cola, igual que el push: quien escanea en la puerta no espera a
 * ningún servicio externo, y si alguno falla la asistencia ya quedó guardada.
 */
class EnviarPorCanalesExternos implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public readonly int $mensajeId) {}

    public function handle(WhatsApp $whatsapp, Sofia $sofia): void
    {
        $mensaje = Mensaje::query()->with(['apoderado', 'alumno.seccion.grado'])->find($this->mensajeId);

        if ($mensaje === null) {
            return;
        }

        $telefono = $mensaje->apoderado->telefono;

        if ($whatsapp->disponible()) {
            $whatsapp->enviar($telefono, "*{$mensaje->titulo}*\n{$mensaje->cuerpo}");
        }

        // Sofía solo entiende ingresos y salidas; una tardanza es un ingreso.
        $evento = match ($mensaje->tipo) {
            TipoMensaje::Entrada, TipoMensaje::Tardanza => 'entrada',
            TipoMensaje::Salida => 'salida',
            default => null,
        };

        if ($evento !== null && $mensaje->alumno !== null && $sofia->disponible()) {
            $sofia->avisarAsistencia(
                $evento,
                $mensaje->alumno->codigo,
                $mensaje->alumno->nombreCompleto(),
                $mensaje->alumno->aula(),
                $mensaje->apoderado->nombres,
                $telefono,
                $mensaje->created_at->toDateString(),
                $mensaje->created_at->format('H:i:s'),
            );
        }
    }
}
