<?php

namespace App\Services\Canales;

use App\Support\Integraciones;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aviso a Sofía, la asistente de WhatsApp del colegio. El sistema no habla con
 * WhatsApp: le pasa a Sofía el ingreso o la salida y ella le escribe al apoderado.
 */
class Sofia
{
    public function __construct(private readonly Integraciones $config) {}

    public function disponible(): bool
    {
        return $this->config->activo('sofia_activo')
            && $this->config->texto('sofia_url') !== ''
            && $this->config->texto('sofia_token') !== '';
    }

    /**
     * @param  string  $evento  'entrada' | 'salida'
     * @param  string  $hora  'HH:MM:SS'
     */
    public function avisarAsistencia(string $evento, string $codigo, string $alumno, ?string $aula, string $apoderado, string $telefono, string $fecha, string $hora): bool
    {
        $telefono = Integraciones::telefonoInternacional($telefono);

        if (! $this->disponible() || $telefono === '') {
            return false;
        }

        try {
            $respuesta = Http::withToken($this->config->texto('sofia_token'))->timeout(5)->post($this->config->texto('sofia_url'), [
                'evento' => $evento,
                'alumno' => $alumno,
                'grado' => (string) $aula,
                'apoderado' => $apoderado,
                'telefono' => $telefono,
                'hora' => $fecha.'T'.$hora.'-05:00',
                // Si el mismo evento llega dos veces, Sofía lo descarta y el
                // apoderado no recibe el aviso repetido.
                'referencia' => $codigo.'-'.str_replace('-', '', $fecha).'-'.$evento,
            ]);

            if (! $respuesta->successful()) {
                Log::warning('Sofía rechazó el aviso', ['http' => $respuesta->status()]);
            }

            return $respuesta->successful();
        } catch (Throwable $e) {
            Log::warning('Sofía: no se pudo conectar', ['motivo' => $e->getMessage()]);

            return false;
        }
    }
}
