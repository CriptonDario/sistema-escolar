<?php

namespace App\Enums;

enum TipoPermiso: string
{
    case SalidaAnticipada = 'salida_anticipada';
    case IngresoTarde = 'ingreso_tarde';
    case Inasistencia = 'inasistencia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SalidaAnticipada => 'Salida anticipada',
            self::IngresoTarde => 'Ingreso tarde',
            self::Inasistencia => 'Inasistencia justificada',
        };
    }

    /** La inasistencia cubre el día entero; los otros dos llevan una hora. */
    public function requiereHora(): bool
    {
        return $this !== self::Inasistencia;
    }
}
