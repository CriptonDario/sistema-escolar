<?php

namespace App\Support;

/**
 * Identidad del colegio que se ve en pantalla: nombre, logo y color.
 * Sale de la configuración (tabla `ajustes`), así cada colegio tiene la suya
 * sin tocar código.
 */
final readonly class Marca
{
    private const COLOR_POR_DEFECTO = '#0d6efd';

    public string $color;

    public function __construct(
        public string $nombre,
        public ?string $logo,
        string $color,
    ) {
        $this->color = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : self::COLOR_POR_DEFECTO;
    }

    public static function desde(Ajustes $ajustes): self
    {
        return new self(
            $ajustes->nombreColegio(),
            $ajustes->obtener('logo_url') ?: null,
            (string) $ajustes->obtener('color_primario', self::COLOR_POR_DEFECTO),
        );
    }

    /** El color un poco más oscuro, para hover y degradados. */
    public function colorOscuro(): string
    {
        return '#'.implode('', array_map(
            fn (int $canal) => str_pad(dechex((int) round($canal * 0.82)), 2, '0', STR_PAD_LEFT),
            $this->canales(),
        ));
    }

    /** "253,13,13": para armar fondos suaves con rgba() en CSS. */
    public function colorRgb(): string
    {
        return implode(',', $this->canales());
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function canales(): array
    {
        return array_map('hexdec', str_split(substr($this->color, 1), 2));
    }
}
