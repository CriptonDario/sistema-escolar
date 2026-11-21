<?php

namespace App\Services\Pagos;

use App\Enums\TipoConcepto;
use App\Models\Alumno;
use App\Models\Pago;
use App\Support\Ajustes;

/**
 * Qué pagó y qué debe un alumno en un año: sus pagos y, mes por mes, si la
 * pensión está pagada, vencida o por vencer.
 */
class EstadoDeCuenta
{
    public function __construct(private readonly Ajustes $ajustes) {}

    public function de(Alumno $alumno, int $anio): array
    {
        $pagos = Pago::query()->vigentes()
            ->where('alumno_id', $alumno->id)->where('anio', $anio)
            ->with('concepto')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->get();

        $mesesPagados = $pagos
            ->filter(fn (Pago $pago) => $pago->concepto->tipo === TipoConcepto::Pension && $pago->mes !== null)
            ->pluck('mes')->all();

        $pensiones = [];
        foreach ($this->mesesDePension() as $mes) {
            $pagada = in_array($mes, $mesesPagados, true);
            $vencida = ! $pagada && ($anio < now()->year || ($anio === now()->year && $mes < now()->month));

            $pensiones[] = [
                'mes' => $mes,
                'nombre' => Pago::MESES[$mes],
                'estado' => $pagada ? 'pagada' : ($vencida ? 'vencida' : 'pendiente'),
            ];
        }

        return [
            'anio' => $anio,
            'total_pagado' => round($pagos->sum('monto'), 2),
            'pensiones' => $pensiones,
            'pensiones_vencidas' => count(array_filter($pensiones, fn ($p) => $p['estado'] === 'vencida')),
            'pagos' => $pagos->map(fn (Pago $pago) => [
                'recibo' => $pago->numeroRecibo(),
                'detalle' => $pago->detalle(),
                'monto' => $pago->monto,
                'fecha' => $pago->fecha->toDateString(),
                'metodo' => $pago->metodo->value,
            ])->values()->all(),
        ];
    }

    /**
     * Meses del año en que se cobra pensión. Por defecto de marzo a diciembre;
     * el colegio puede cambiarlo en Configuración.
     *
     * @return list<int>
     */
    public function mesesDePension(): array
    {
        $inicio = max(1, min(12, (int) $this->ajustes->obtener('pension_mes_inicio', 3)));
        $fin = max($inicio, min(12, (int) $this->ajustes->obtener('pension_mes_fin', 12)));

        return range($inicio, $fin);
    }
}
