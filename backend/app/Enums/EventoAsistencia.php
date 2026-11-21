<?php

namespace App\Enums;

/**
 * Qué ocurrió al escanear un código. Solo Entrada, Tardanza y Salida cambian
 * datos; los demás son respuestas informativas para quien escanea.
 */
enum EventoAsistencia: string
{
    case Entrada = 'entrada';
    case Tardanza = 'tardanza';
    case Salida = 'salida';
    case EntradaYaRegistrada = 'entrada_ya_registrada';
    case SalidaYaRegistrada = 'salida_ya_registrada';

    public function registraCambio(): bool
    {
        return in_array($this, [self::Entrada, self::Tardanza, self::Salida], true);
    }
}
