@extends('layouts.panel')

@section('titulo', 'Pagos')
@section('icono', 'bi-cash-coin')

@section('contenido')
<datalist id="lista-alumnos">
    @foreach ($alumnos as $alumno)
        <option data-id="{{ $alumno->id }}" value="{{ $alumno->codigo }} — {{ $alumno->apellidos }}, {{ $alumno->nombres }}"></option>
    @endforeach
</datalist>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Registrar pago</div>
            <div class="card-body">
                @if ($conceptos->isEmpty())
                    <p class="text-muted mb-0">Primero crea los <a href="{{ route('conceptos.index') }}">conceptos de pago</a>.</p>
                @else
                <form action="{{ route('pagos.store') }}" method="POST" id="form-pago">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="alumno_texto">Alumno *</label>
                        <input type="text" id="alumno_texto" class="form-control" list="lista-alumnos" required placeholder="Código o apellido">
                        <input type="hidden" name="alumno_id" id="alumno_id">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="concepto_id">Concepto *</label>
                        <select name="concepto_id" id="concepto_id" class="form-select" required>
                            @foreach ($conceptos as $concepto)
                                <option value="{{ $concepto->id }}" data-monto="{{ number_format($concepto->monto, 2, '.', '') }}" data-pension="{{ $concepto->tipo === \App\Enums\TipoConcepto::Pension ? 1 : 0 }}"
                                    @selected(old('concepto_id') == $concepto->id)>{{ $concepto->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-5 mb-3">
                            <label class="form-label fw-bold" for="anio">Año *</label>
                            <input type="number" name="anio" id="anio" class="form-control" min="2000" max="2100" required value="{{ old('anio', now()->year) }}">
                        </div>
                        <div class="col-7 mb-3" id="grupo-mes">
                            <label class="form-label fw-bold" for="mes">Mes de la pensión *</label>
                            <select name="mes" id="mes" class="form-select">
                                @foreach ($meses as $numero => $nombre)
                                    <option value="{{ $numero }}" @selected((int) old('mes', now()->month) === $numero)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="monto">Monto (S/) *</label>
                            <input type="number" name="monto" id="monto" class="form-control" min="0.01" step="0.01" required value="{{ old('monto') }}">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="fecha">Fecha *</label>
                            <input type="date" name="fecha" id="fecha" class="form-control" required value="{{ old('fecha', today()->toDateString()) }}" max="{{ today()->toDateString() }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="metodo">Método *</label>
                            <select name="metodo" id="metodo" class="form-select" required>
                                @foreach ($metodos as $metodo)
                                    <option value="{{ $metodo->value }}" @selected(old('metodo') === $metodo->value)>{{ $metodo->etiqueta() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="numero_operacion">N.° operación</label>
                            <input type="text" name="numero_operacion" id="numero_operacion" class="form-control" maxlength="50" value="{{ old('numero_operacion') }}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Registrar y ver recibo</button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label small mb-0" for="desde">Desde</label>
                        <input type="date" name="desde" id="desde" class="form-control form-control-sm" value="{{ $desde }}">
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-0" for="hasta">Hasta</label>
                        <input type="date" name="hasta" id="hasta" class="form-control form-control-sm" value="{{ $hasta }}">
                    </div>
                    <div class="col">
                        <label class="form-label small mb-0" for="q">Alumno</label>
                        <input type="search" name="q" id="q" class="form-control form-control-sm" value="{{ $filtros['q'] ?? '' }}">
                    </div>
                    <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i> Filtrar</button></div>
                </form>
                <div class="mt-2">Cobrado en el periodo: <strong>S/ {{ number_format($total, 2) }}</strong></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="ps-3">Recibo</th><th>Alumno</th><th>Concepto</th><th class="text-end">Monto</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($pagos as $pago)
                        <tr class="{{ $pago->estaVigente() ? '' : 'inactivo' }}">
                            <td class="ps-3" style="white-space: nowrap">
                                <a href="{{ route('pagos.recibo', $pago) }}">N.° {{ $pago->numeroRecibo() }}</a>
                                <br><small class="text-muted">{{ $pago->fecha->format('d/m/Y') }}</small>
                            </td>
                            <td>
                                <a href="{{ route('pagos.estado', $pago->alumno) }}" class="fw-bold text-decoration-none" title="Ver estado de cuenta">{{ $pago->alumno->nombreCompleto() }}</a>
                            </td>
                            <td>
                                {{ $pago->detalle() }}
                                @unless ($pago->estaVigente())<span class="badge bg-secondary">Anulado</span>@endunless
                                <br><small class="text-muted">{{ $pago->metodo->etiqueta() }}</small>
                            </td>
                            <td class="text-end">S/ {{ number_format($pago->monto, 2) }}</td>
                            <td class="text-end pe-3">
                                @if ($pago->estaVigente())
                                    <form action="{{ route('pagos.destroy', $pago) }}" method="POST" onsubmit="return confirm('¿Anular este pago?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular pago"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted p-4">Sin pagos en este periodo.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($pagos->hasPages())
                <div class="card-footer bg-white">{{ $pagos->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const formPago = document.getElementById('form-pago');
if (formPago) {
    const idPorTexto = {};
    document.querySelectorAll('#lista-alumnos option').forEach(function (o) { idPorTexto[o.value] = o.dataset.id; });
    const concepto = document.getElementById('concepto_id');
    const monto = document.getElementById('monto');

    // El concepto propone su monto y decide si se pide el mes.
    function ajustarConcepto(proponerMonto) {
        const opcion = concepto.selectedOptions[0];
        const esPension = opcion.dataset.pension === '1';
        document.getElementById('grupo-mes').style.display = esPension ? '' : 'none';
        document.getElementById('mes').disabled = !esPension;
        if (proponerMonto || monto.value === '') { monto.value = opcion.dataset.monto; }
    }
    concepto.addEventListener('change', function () { ajustarConcepto(true); });
    ajustarConcepto(false);

    formPago.addEventListener('submit', function (ev) {
        const id = idPorTexto[document.getElementById('alumno_texto').value.trim()];
        if (!id) { ev.preventDefault(); alert('Elige al alumno de la lista de sugerencias.'); return; }
        document.getElementById('alumno_id').value = id;
    });
}
</script>
@endpush
