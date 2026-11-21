<?php

namespace App\Services\Mensajeria;

use App\Enums\TipoMensaje;
use App\Jobs\EnviarPorCanalesExternos;
use App\Jobs\EnviarPush;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Mensaje;
use Illuminate\Support\Str;

/**
 * Envía mensajes a los apoderados en la app.
 *
 * Un mensaje tiene dos mitades:
 *   1. La fila en `mensajes`: la bandeja que la app lee. Se escribe en el acto
 *      y garantiza que el mensaje exista aunque el teléfono esté apagado.
 *   2. La notificación push y, si el colegio los activó, WhatsApp y Sofía.
 *      Dependen de servicios externos, así que salen por la cola y nunca
 *      hacen esperar a quien escanea.
 */
class Mensajero
{
    /**
     * Deja el mensaje en la bandeja de cada apoderado del alumno.
     * Devuelve cuántos apoderados lo recibieron.
     */
    public function avisarApoderadosDe(Alumno $alumno, TipoMensaje $tipo, string $titulo, string $cuerpo): int
    {
        $apoderados = $alumno->apoderados()
            ->whereHas('user', fn ($q) => $q->where('activo', true))
            ->get();

        foreach ($apoderados as $apoderado) {
            $this->enviar($apoderado, $tipo, $titulo, $cuerpo, $alumno);
        }

        return $apoderados->count();
    }

    public function enviar(Apoderado $apoderado, TipoMensaje $tipo, string $titulo, string $cuerpo, ?Alumno $alumno = null): Mensaje
    {
        $mensaje = $apoderado->mensajes()->create([
            'alumno_id' => $alumno?->id,
            'tipo' => $tipo,
            'titulo' => Str::limit($titulo, 117),
            'cuerpo' => Str::limit($cuerpo, 497),
        ]);

        if ($apoderado->user_id !== null) {
            EnviarPush::dispatch($apoderado->user_id, $mensaje->titulo, $mensaje->cuerpo, [
                'tipo' => $tipo->value,
                'mensaje_id' => (string) $mensaje->id,
                'alumno_id' => (string) ($alumno->id ?? ''),
            ]);
        }

        // WhatsApp y Sofía, si el colegio los activó.
        EnviarPorCanalesExternos::dispatch($mensaje->id);

        return $mensaje;
    }
}
