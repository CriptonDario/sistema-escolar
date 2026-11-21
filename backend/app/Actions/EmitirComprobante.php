<?php

namespace App\Actions;

use App\Models\Comprobante;
use App\Models\Pago;
use App\Models\User;
use App\Services\Sunat\Nubefact;
use App\Support\Integraciones;
use Illuminate\Support\Facades\DB;

/**
 * Emite la boleta o factura electrónica de un pago.
 */
class EmitirComprobante
{
    public function __construct(
        private readonly Nubefact $nubefact,
        private readonly Integraciones $config,
    ) {}

    /**
     * @param  array{tipo: string, cliente_tipo_doc: string, cliente_numero_doc: ?string, cliente_nombre: string}  $datos
     */
    public function ejecutar(Pago $pago, array $datos, User $emitidoPor): Comprobante
    {
        $serie = $datos['tipo'] === Comprobante::FACTURA
            ? $this->config->texto('sunat_serie_factura', 'F001')
            : $this->config->texto('sunat_serie_boleta', 'B001');

        // El correlativo se toma con la serie bloqueada: dos emisiones a la vez
        // no pueden llevarse el mismo número, y la clave única lo garantiza.
        $comprobante = DB::transaction(function () use ($pago, $datos, $serie, $emitidoPor) {
            $ultimo = Comprobante::query()->where('serie', $serie)->lockForUpdate()->max('correlativo');

            return Comprobante::query()->create($datos + [
                'pago_id' => $pago->id,
                'serie' => $serie,
                'correlativo' => ((int) $ultimo) + 1,
                'total' => $pago->monto,
                'emitido_por' => $emitidoPor->id,
            ]);
        });

        return $this->nubefact->emitir($comprobante);
    }

    /** Reintenta el envío de un comprobante que no llegó a SUNAT, con su mismo número. */
    public function reenviar(Comprobante $comprobante): Comprobante
    {
        return $this->nubefact->emitir($comprobante);
    }
}
