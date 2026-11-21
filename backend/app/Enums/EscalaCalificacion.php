<?php

namespace App\Enums;

/**
 * Con qué se califica: con números o con letras.
 */
enum EscalaCalificacion: string
{
    case Numerica = 'numerica';
    case Literal = 'literal';

    public const NOTA_MAXIMA = 20;

    /** Letras de la escala literal, de mayor a menor logro. */
    public const LETRAS = [
        'AD' => 'Logro destacado',
        'A' => 'Logro esperado',
        'B' => 'En proceso',
        'C' => 'En inicio',
    ];

    public function etiqueta(): string
    {
        return match ($this) {
            self::Numerica => 'Numérica (0 a '.self::NOTA_MAXIMA.')',
            self::Literal => 'Literal (AD, A, B, C)',
        };
    }

    /** Regla de validación para un valor de esta escala. */
    public function reglas(): array
    {
        return match ($this) {
            self::Numerica => ['nullable', 'numeric', 'between:0,'.self::NOTA_MAXIMA],
            self::Literal => ['nullable', 'string', 'in:'.implode(',', array_keys(self::LETRAS))],
        };
    }
}
