<?php

namespace App\Services\Sunat;

use App\Models\Comprobante;
use App\Support\Integraciones;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Emisión de comprobantes electrónicos a SUNAT a través de Nubefact.
 *
 * Nubefact (un PSE/OSE autorizado) recibe el comprobante en JSON, lo firma,
 * lo envía a SUNAT y devuelve el PDF. El colegio obtiene en su cuenta de
 * Nubefact la RUTA y el TOKEN que se cargan en Configuración → Integraciones.
 *
 * El servicio educativo está exonerado de IGV, así que el comprobante sale
 * como operación exonerada salvo que el colegio configure lo contrario.
 */
class Nubefact
{
    private const TIPO_DE_COMPROBANTE = [Comprobante::FACTURA => 1, Comprobante::BOLETA => 2];

    public function __construct(private readonly Integraciones $config) {}

    public function disponible(): bool
    {
        return $this->config->activo('sunat_activo')
            && $this->config->texto('sunat_ruta') !== ''
            && $this->config->texto('sunat_token') !== '';
    }

    /**
     * Envía el comprobante y deja en él el resultado: aceptado, rechazado o error.
     */
    public function emitir(Comprobante $comprobante): Comprobante
    {
        try {
            $respuesta = Http::withHeaders(['Authorization' => 'Token token="'.$this->config->texto('sunat_token').'"'])
                ->timeout(25)
                ->post($this->config->texto('sunat_ruta'), $this->cuerpo($comprobante));
        } catch (Throwable $e) {
            return tap($comprobante)->update(['estado' => Comprobante::ERROR, 'mensaje_sunat' => 'No se pudo conectar con Nubefact. Se puede reenviar.']);
        }

        // Nubefact responde con "errors" cuando no acepta el pedido.
        if (! $respuesta->successful() || $respuesta->json('errors')) {
            return tap($comprobante)->update([
                'estado' => Comprobante::RECHAZADO,
                'mensaje_sunat' => mb_substr((string) ($respuesta->json('errors') ?? 'Nubefact respondió '.$respuesta->status()), 0, 250),
            ]);
        }

        return tap($comprobante)->update([
            'estado' => Comprobante::ACEPTADO,
            'mensaje_sunat' => mb_substr((string) ($respuesta->json('sunat_description') ?? 'Enviado a SUNAT'), 0, 250),
            'enlace_pdf' => $respuesta->json('enlace_del_pdf') ?: $respuesta->json('enlace'),
        ]);
    }

    /** El comprobante en el formato JSON de Nubefact. */
    public function cuerpo(Comprobante $comprobante): array
    {
        $comprobante->loadMissing('pago.concepto', 'pago.alumno');
        $gravado = $this->config->texto('sunat_afectacion', 'exonerado') === 'gravado';
        $total = round($comprobante->total, 2);
        $base = $gravado ? round($total / 1.18, 2) : $total;
        $igv = round($total - $base, 2);

        return [
            'operacion' => 'generar_comprobante',
            'tipo_de_comprobante' => self::TIPO_DE_COMPROBANTE[$comprobante->tipo],
            'serie' => $comprobante->serie,
            'numero' => $comprobante->correlativo,
            'sunat_transaction' => 1,
            'cliente_tipo_de_documento' => $comprobante->cliente_tipo_doc,
            'cliente_numero_de_documento' => $comprobante->cliente_numero_doc ?: '-',
            'cliente_denominacion' => $comprobante->cliente_nombre,
            'cliente_direccion' => '',
            'fecha_de_emision' => $comprobante->created_at->format('d-m-Y'),
            'moneda' => 1,
            'porcentaje_de_igv' => 18.00,
            'total_gravada' => $gravado ? $base : '',
            'total_exonerada' => $gravado ? '' : $total,
            'total_igv' => $igv,
            'total' => $total,
            'enviar_automaticamente_a_la_sunat' => true,
            'enviar_automaticamente_al_cliente' => false,
            'items' => [[
                'unidad_de_medida' => 'ZZ',
                'codigo' => 'P'.$comprobante->pago->concepto_id,
                'descripcion' => $comprobante->pago->detalle().' — '.$comprobante->pago->alumno->nombreCompleto(),
                'cantidad' => 1,
                'valor_unitario' => $base,
                'precio_unitario' => $total,
                'subtotal' => $base,
                'tipo_de_igv' => $gravado ? 1 : 8,
                'igv' => $igv,
                'total' => $total,
                'anticipo_regularizacion' => false,
            ]],
        ];
    }
}
