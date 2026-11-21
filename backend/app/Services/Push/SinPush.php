<?php

namespace App\Services\Push;

/**
 * Canal nulo: se usa cuando el colegio aún no configuró Firebase. Los mensajes
 * igual quedan en la bandeja de la app; solo falta el aviso instantáneo.
 */
class SinPush implements PushGateway
{
    public function disponible(): bool
    {
        return false;
    }

    public function enviar(string $tokenDispositivo, string $titulo, string $cuerpo, array $datos = []): ResultadoPush
    {
        return ResultadoPush::Error;
    }
}
