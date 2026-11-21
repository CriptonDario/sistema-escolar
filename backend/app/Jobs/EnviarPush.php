<?php

namespace App\Jobs;

use App\Models\Dispositivo;
use App\Services\Push\PushGateway;
use App\Services\Push\ResultadoPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Manda una notificación push a todos los teléfonos de una cuenta.
 *
 * Va por la cola: quien escanea en la puerta no espera a Firebase, y un push
 * que falla nunca afecta al registro de asistencia, que ya quedó guardado.
 */
class EnviarPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, string>  $datos
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $titulo,
        public readonly string $cuerpo,
        public readonly array $datos = [],
    ) {}

    public function handle(PushGateway $push): void
    {
        if (! $push->disponible()) {
            return;
        }

        Dispositivo::query()->where('user_id', $this->userId)->each(function (Dispositivo $dispositivo) use ($push) {
            $resultado = $push->enviar($dispositivo->token, $this->titulo, $this->cuerpo, $this->datos);

            if ($resultado === ResultadoPush::TokenInvalido) {
                $dispositivo->delete();
            }
        });
    }
}
