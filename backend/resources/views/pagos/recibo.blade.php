@extends('layouts.base')

@section('titulo', 'Recibo N.° '.$pago->numeroRecibo())

@push('estilos')
<style>
    body { background: #fff !important; }
    .recibo { max-width: 620px; margin: 2rem auto; border: 1px solid #dee2e6; border-radius: 12px; padding: 2rem; }
    @media print { .no-imprimir { display: none !important; } .recibo { border: 0; margin: 0; } }
</style>
@endpush

@section('cuerpo')
<div class="recibo">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
        <div class="d-flex align-items-center gap-3">
            @if ($marca->logo)<img src="{{ asset($marca->logo) }}" alt="" style="height: 60px">@endif
            <div>
                <div class="fw-bold fs-5">{{ $marca->nombre }}</div>
                <div class="text-muted small">Recibo de pago</div>
            </div>
        </div>
        <div class="text-end">
            <div class="fw-bold fs-5">N.° {{ $pago->numeroRecibo() }}</div>
            <div class="text-muted small">{{ $pago->fecha->format('d/m/Y') }}</div>
        </div>
    </div>

    @unless ($pago->estaVigente())
        <div class="alert alert-danger text-center fw-bold">RECIBO ANULADO</div>
    @endunless

    <table class="table table-borderless mb-3">
        <tr><th style="width: 35%">Alumno</th><td>{{ $pago->alumno->nombreCompleto() }} ({{ $pago->alumno->codigo }})</td></tr>
        <tr><th>Aula</th><td>{{ $pago->alumno->aula() ?? '—' }}</td></tr>
        <tr><th>Concepto</th><td>{{ $pago->detalle() }}</td></tr>
        <tr><th>Método</th><td>{{ $pago->metodo->etiqueta() }}@if ($pago->numero_operacion) · Op. {{ $pago->numero_operacion }}@endif</td></tr>
        @if ($pago->observacion)<tr><th>Observación</th><td>{{ $pago->observacion }}</td></tr>@endif
    </table>

    <div class="d-flex justify-content-between align-items-center bg-light rounded p-3">
        <span class="fw-bold">Total pagado</span>
        <span class="fw-bold fs-4">S/ {{ number_format($pago->monto, 2) }}</span>
    </div>

    @if (session('ok'))<div class="alert alert-success mt-3 no-imprimir">{{ session('ok') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger mt-3 no-imprimir">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger mt-3 no-imprimir">{{ $errors->first() }}</div>@endif

    {{-- Comprobante electrónico --}}
    @php($comprobante = $pago->comprobantes->first())
    @if ($comprobante)
        <div class="border rounded p-3 mt-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>{{ ucfirst($comprobante->tipo) }} electrónica {{ $comprobante->numero() }}</strong>
                    <span class="badge {{ ['aceptado' => 'bg-success', 'rechazado' => 'bg-danger', 'error' => 'bg-warning text-dark', 'pendiente' => 'bg-secondary'][$comprobante->estado] }}">{{ ucfirst($comprobante->estado) }}</span>
                    <br><small class="text-muted">{{ $comprobante->cliente_nombre }} · {{ $comprobante->mensaje_sunat }}</small>
                </div>
                <div class="no-imprimir">
                    @if ($comprobante->enlace_pdf)
                        <a href="{{ $comprobante->enlace_pdf }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-pdf"></i> Ver PDF</a>
                    @endif
                    @if ($comprobante->sePuedeReenviar())
                        <form action="{{ route('comprobantes.reenviar', $comprobante) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning">Reenviar a SUNAT</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($facturacionActiva && $pago->estaVigente() && (! $comprobante || $comprobante->estado === 'rechazado'))
        @php($apoderado = $pago->alumno->apoderados->first())
        <form action="{{ route('pagos.comprobante', $pago) }}" method="POST" class="border rounded p-3 mt-3 no-imprimir">
            @csrf
            <div class="fw-bold mb-2">Emitir comprobante electrónico</div>
            <div class="row g-2">
                <div class="col-sm-4">
                    <label class="form-label small mb-0" for="tipo">Tipo</label>
                    <select name="tipo" id="tipo" class="form-select form-select-sm">
                        <option value="boleta" @selected(old('tipo') !== 'factura')>Boleta</option>
                        <option value="factura" @selected(old('tipo') === 'factura')>Factura</option>
                    </select>
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-0" for="cliente_tipo_doc">Documento</label>
                    <select name="cliente_tipo_doc" id="cliente_tipo_doc" class="form-select form-select-sm">
                        <option value="1" @selected(old('cliente_tipo_doc', '1') === '1')>DNI</option>
                        <option value="6" @selected(old('cliente_tipo_doc') === '6')>RUC</option>
                        <option value="-" @selected(old('cliente_tipo_doc') === '-')>Sin documento</option>
                    </select>
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-0" for="cliente_numero_doc">Número</label>
                    <input type="text" name="cliente_numero_doc" id="cliente_numero_doc" class="form-control form-control-sm" maxlength="11" inputmode="numeric" value="{{ old('cliente_numero_doc', $apoderado?->dni) }}">
                </div>
                <div class="col-12">
                    <label class="form-label small mb-0" for="cliente_nombre">Nombre o razón social</label>
                    <input type="text" name="cliente_nombre" id="cliente_nombre" class="form-control form-control-sm" maxlength="150" required value="{{ old('cliente_nombre', $apoderado?->nombres ?? $pago->alumno->nombreCompleto()) }}">
                </div>
            </div>
            <button type="submit" class="btn btn-sm btn-primary mt-2" onclick="return confirm('¿Emitir el comprobante y enviarlo a SUNAT? No se puede deshacer.')">Emitir y enviar a SUNAT</button>
        </form>
    @endif

    <div class="d-flex gap-2 mt-4 no-imprimir">
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
        <a href="{{ route('pagos.index') }}" class="btn btn-outline-secondary">Volver a pagos</a>
        <a href="{{ route('pagos.estado', $pago->alumno) }}" class="btn btn-outline-secondary">Estado de cuenta</a>
    </div>
</div>
@endsection
