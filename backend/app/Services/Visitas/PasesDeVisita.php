<?php

namespace App\Services\Visitas;

use App\Models\Visita;
use App\Support\Ajustes;

/**
 * Pases de visitante: credenciales genéricas y numeradas (VIS-01, VIS-02…)
 * que no llevan nombre. El guardia entrega una al registrar el ingreso y la
 * recoge a la salida; escanearla en la puerta marca esa salida.
 *
 * Un pase está "en uso" mientras la visita que lo tiene no haya salido.
 */
class PasesDeVisita
{
    public const MAXIMO = 99;

    public function __construct(private readonly Ajustes $ajustes) {}

    /** Cuántos pases tiene impresos el colegio. */
    public function cantidad(): int
    {
        return max(1, min(self::MAXIMO, (int) $this->ajustes->obtener('pases_visita', 10)));
    }

    public function fijarCantidad(int $cantidad): void
    {
        $this->ajustes->guardar('pases_visita', (string) max(1, min(self::MAXIMO, $cantidad)));
    }

    /** @return array<int, string> */
    public function codigos(): array
    {
        return array_map(fn (int $numero) => sprintf('VIS-%02d', $numero), range(1, $this->cantidad()));
    }

    /** ¿Lo escaneado es un pase de visitante (y no el carné de una persona)? */
    public function esPase(string $codigo): bool
    {
        return preg_match('/^VIS-\d{2}$/', self::normalizar($codigo)) === 1;
    }

    public static function normalizar(string $codigo): string
    {
        return mb_strtoupper(trim($codigo));
    }

    /** @return array<int, string> */
    public function libres(): array
    {
        $enUso = Visita::query()->whereNull('salida')->whereNotNull('pase')->pluck('pase')->all();

        return array_values(array_diff($this->codigos(), $enUso));
    }

    /** La visita que tiene ese pase ahora mismo, si alguien lo tiene. */
    public function visitaCon(string $codigo): ?Visita
    {
        return Visita::query()->whereNull('salida')->where('pase', self::normalizar($codigo))->latest('entrada')->first();
    }

    /** El visitante devuelve el pase: se marca su salida. Null si nadie lo tenía. */
    public function devolver(string $codigo): ?Visita
    {
        $visita = $this->visitaCon($codigo);
        $visita?->update(['salida' => now()]);

        return $visita;
    }

    /**
     * Lo que se le responde al kiosco o a la app cuando escanean un pase.
     *
     * @return array<string, mixed>
     */
    public function alEscanear(string $codigo): array
    {
        $visita = $this->devolver($codigo);
        $pase = self::normalizar($codigo);

        if ($visita === null) {
            abort(response()->json(['message' => "El pase {$pase} no está entregado a ningún visitante.", 'codigo' => 'pase_sin_uso'], 422));
        }

        return [
            'evento' => 'salida',
            'registrado' => true,
            'mensaje' => "Salida de {$visita->nombre} (visitante)",
            'entrada' => $visita->entrada->format('H:i'),
            'salida' => $visita->salida->format('H:i'),
            'nombre' => $visita->nombre,
            'detalle' => "Visitante · pase {$pase}",
        ];
    }
}
