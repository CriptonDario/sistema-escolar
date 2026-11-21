<?php

namespace App\Services\Asistencia;

use App\Enums\EventoAsistencia;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Personal;

/**
 * Lo que pasó al escanear un código: a quién se marcó y qué evento fue.
 */
final readonly class ResultadoMarca
{
    public function __construct(
        public EventoAsistencia $evento,
        public Alumno|Personal $persona,
        public Asistencia $asistencia,
        public string $mensaje,
    ) {}

    public function esAlumno(): bool
    {
        return $this->persona instanceof Alumno;
    }
}
