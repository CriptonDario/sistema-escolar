<?php

namespace App\Support;

use App\Models\Ajuste;

/**
 * Configuración del colegio que la dirección puede cambiar desde el sistema.
 *
 * Lo guardado en la tabla `ajustes` manda; si una clave no está, se usa el
 * valor de arranque de config/colegio.php.
 */
class Ajustes
{
    /** @var array<string, string|null>|null */
    private ?array $cache = null;

    public function obtener(string $clave, mixed $porDefecto = null): mixed
    {
        $this->cache ??= Ajuste::query()->pluck('valor', 'clave')->all();

        return $this->cache[$clave] ?? $porDefecto;
    }

    public function guardar(string $clave, ?string $valor): void
    {
        Ajuste::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);

        if ($this->cache !== null) {
            $this->cache[$clave] = $valor;
        }
    }

    public function nombreColegio(): string
    {
        return (string) $this->obtener('nombre_colegio', config('app.name'));
    }

    /** Hora límite de ingreso puntual, como "HH:MM". */
    public function horaEntrada(): string
    {
        return substr((string) $this->obtener('hora_entrada', config('colegio.asistencia.hora_entrada')), 0, 5);
    }

    public function toleranciaMinutos(): int
    {
        return max(0, (int) $this->obtener('tolerancia_minutos', config('colegio.asistencia.tolerancia_minutos')));
    }

    public function minutosMinimosParaSalida(): int
    {
        return max(0, (int) $this->obtener(
            'minutos_minimos_para_salida',
            config('colegio.asistencia.minutos_minimos_para_salida')
        ));
    }
}
