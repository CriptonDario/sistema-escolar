<?php

namespace App\Support;

/**
 * Los periodos de evaluación del año escolar.
 *
 * Cada colegio elige en Configuración si evalúa por bimestres (4), trimestres
 * (3) o semestres (2). Las notas guardan solo el número de periodo.
 */
class Periodos
{
    private const NOMBRES = [2 => 'Semestre', 3 => 'Trimestre', 4 => 'Bimestre'];

    public function __construct(private readonly Ajustes $ajustes) {}

    /** Cuántos periodos tiene el año: 2, 3 o 4. */
    public function cantidad(): int
    {
        $cantidad = (int) $this->ajustes->obtener('periodos_por_anio', 3);

        return isset(self::NOMBRES[$cantidad]) ? $cantidad : 3;
    }

    /** "Trimestre" */
    public function nombre(): string
    {
        return self::NOMBRES[$this->cantidad()];
    }

    /** @return array<int, string> [1 => 'Trimestre 1', 2 => 'Trimestre 2', ...] */
    public function todos(): array
    {
        $periodos = [];
        foreach (range(1, $this->cantidad()) as $numero) {
            $periodos[$numero] = $this->nombre().' '.$numero;
        }

        return $periodos;
    }

    /** @return array<int, string> opciones para la pantalla de configuración */
    public static function opciones(): array
    {
        return [4 => 'Bimestres (4 al año)', 3 => 'Trimestres (3 al año)', 2 => 'Semestres (2 al año)'];
    }
}
