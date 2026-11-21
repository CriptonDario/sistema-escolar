<?php

namespace App\Enums;

enum TipoPersonal: string
{
    case Docente = 'docente';
    case Administrativo = 'administrativo';
    case Apoyo = 'apoyo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Docente => 'Docente',
            self::Administrativo => 'Administrativo',
            self::Apoyo => 'Apoyo',
        };
    }
}
