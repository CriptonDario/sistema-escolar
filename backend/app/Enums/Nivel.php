<?php

namespace App\Enums;

enum Nivel: string
{
    case Inicial = 'inicial';
    case Primaria = 'primaria';
    case Secundaria = 'secundaria';

    public function etiqueta(): string
    {
        return ucfirst($this->value);
    }

    /** Letra que identifica al nivel dentro del código del alumno (2SB01). */
    public function letra(): string
    {
        return strtoupper($this->value[0]);
    }

    /** Posición para ordenar: inicial, primaria, secundaria. */
    public function orden(): int
    {
        return match ($this) {
            self::Inicial => 1,
            self::Primaria => 2,
            self::Secundaria => 3,
        };
    }
}
