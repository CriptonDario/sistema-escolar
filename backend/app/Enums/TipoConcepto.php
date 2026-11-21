<?php

namespace App\Enums;

enum TipoConcepto: string
{
    case Matricula = 'matricula';
    case Pension = 'pension';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Matricula => 'Matrícula',
            self::Pension => 'Pensión',
            self::Otro => 'Otro',
        };
    }
}
