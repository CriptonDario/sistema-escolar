<?php

namespace App\Enums;

enum TipoConducta: string
{
    case Merito = 'merito';
    case Demerito = 'demerito';
    case Observacion = 'observacion';
    case Citacion = 'citacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Merito => 'Mérito',
            self::Demerito => 'Demérito',
            self::Observacion => 'Observación',
            self::Citacion => 'Citación',
        };
    }
}
