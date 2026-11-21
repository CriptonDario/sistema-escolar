<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Un mes del calendario, para las consultas "asistencia de tal mes".
 */
final readonly class Mes
{
    private function __construct(private CarbonImmutable $inicio) {}

    /** Acepta "AAAA-MM"; con cualquier otra cosa devuelve el mes actual. */
    public static function desde(mixed $texto): self
    {
        if (is_string($texto) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $texto)) {
            return new self(CarbonImmutable::createFromFormat('!Y-m', $texto));
        }

        return new self(CarbonImmutable::now()->startOfMonth());
    }

    public function clave(): string
    {
        return $this->inicio->format('Y-m');
    }

    /** @return array{0: string, 1: string} primer y último día del mes */
    public function rango(): array
    {
        return [$this->inicio->toDateString(), $this->inicio->endOfMonth()->toDateString()];
    }
}
