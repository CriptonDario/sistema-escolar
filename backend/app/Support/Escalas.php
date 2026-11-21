<?php

namespace App\Support;

use App\Enums\EscalaCalificacion;
use App\Enums\Nivel;

/**
 * Con qué escala califica cada nivel. Cada colegio lo elige en Configuración;
 * si no lo ha hecho, inicial y primaria usan letras y secundaria números.
 */
class Escalas
{
    private const POR_DEFECTO = [
        'inicial' => EscalaCalificacion::Literal,
        'primaria' => EscalaCalificacion::Literal,
        'secundaria' => EscalaCalificacion::Numerica,
    ];

    public function __construct(private readonly Ajustes $ajustes) {}

    public function del(Nivel $nivel): EscalaCalificacion
    {
        $guardada = EscalaCalificacion::tryFrom((string) $this->ajustes->obtener('escala_'.$nivel->value));

        return $guardada ?? self::POR_DEFECTO[$nivel->value];
    }

    public function guardar(Nivel $nivel, EscalaCalificacion $escala): void
    {
        $this->ajustes->guardar('escala_'.$nivel->value, $escala->value);
    }
}
