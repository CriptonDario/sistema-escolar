<?php

namespace App\Services\Push;

/**
 * Canal por el que sale una notificación push hacia un teléfono.
 */
interface PushGateway
{
    /** ¿Está configurado y puede enviar? */
    public function disponible(): bool;

    /**
     * Envía a un dispositivo.
     *
     * @param  array<string, string>  $datos
     */
    public function enviar(string $tokenDispositivo, string $titulo, string $cuerpo, array $datos = []): ResultadoPush;
}
