<?php

namespace App\Http\Controllers\Web;

use App\Actions\EmitirComprobante;
use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Pago;
use App\Services\Sunat\Nubefact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComprobanteController extends Controller
{
    /** Emite la boleta o factura electrónica de un pago. */
    public function store(Request $request, Pago $pago, EmitirComprobante $emitir, Nubefact $nubefact): RedirectResponse
    {
        if (! $nubefact->disponible()) {
            return back()->with('error', 'La facturación electrónica no está configurada. Complétala en Integraciones.');
        }
        if (! $pago->estaVigente()) {
            return back()->with('error', 'No se emite comprobante de un pago anulado.');
        }
        if ($pago->comprobantes()->where('estado', '!=', Comprobante::RECHAZADO)->exists()) {
            return back()->with('error', 'Este pago ya tiene un comprobante.');
        }

        $esFactura = $request->input('tipo') === Comprobante::FACTURA;

        $datos = $request->validate([
            'tipo' => ['required', Rule::in([Comprobante::BOLETA, Comprobante::FACTURA])],
            // La factura exige RUC; la boleta puede ir con DNI o sin documento.
            'cliente_tipo_doc' => ['required', Rule::in($esFactura ? ['6'] : ['1', '-'])],
            'cliente_numero_doc' => [
                Rule::requiredIf($request->input('cliente_tipo_doc') !== '-'),
                'nullable',
                $esFactura ? 'digits:11' : 'digits:8',
            ],
            'cliente_nombre' => ['required', 'string', 'max:150'],
        ], [
            'cliente_tipo_doc.in' => $esFactura ? 'La factura se emite a un RUC.' : 'La boleta se emite con DNI o sin documento.',
            'cliente_numero_doc.digits' => $esFactura ? 'El RUC tiene 11 dígitos.' : 'El DNI tiene 8 dígitos.',
        ]);

        $comprobante = $emitir->ejecutar($pago, $datos, $request->user());

        return $this->resultado($comprobante);
    }

    public function reenviar(Comprobante $comprobante, EmitirComprobante $emitir): RedirectResponse
    {
        if (! $comprobante->sePuedeReenviar()) {
            return back()->with('error', 'Ese comprobante ya fue procesado por SUNAT.');
        }

        return $this->resultado($emitir->reenviar($comprobante));
    }

    private function resultado(Comprobante $comprobante): RedirectResponse
    {
        return $comprobante->estado === Comprobante::ACEPTADO
            ? back()->with('ok', "Comprobante {$comprobante->numero()} emitido. {$comprobante->mensaje_sunat}")
            : back()->with('error', "Comprobante {$comprobante->numero()}: {$comprobante->mensaje_sunat}");
    }
}
