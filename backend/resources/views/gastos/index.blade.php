@extends('layouts.panel')

@section('titulo', 'Gastos')
@section('icono', 'bi-receipt')

@section('contenido')
<datalist id="lista-categorias">
    @foreach ($categorias as $categoria)<option value="{{ $categoria }}"></option>@endforeach
</datalist>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Registrar gasto</div>
            <div class="card-body">
                <form action="{{ route('gastos.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="categoria">Categoría *</label>
                        <input type="text" name="categoria" id="categoria" class="form-control" list="lista-categorias" maxlength="60" required value="{{ old('categoria') }}" placeholder="Ej: Servicios, Materiales">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="descripcion">Descripción *</label>
                        <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="255" required value="{{ old('descripcion') }}">
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
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Registrar</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Por categoría</div>
            <ul class="list-group list-group-flush">
                @forelse ($porCategoria as $categoria => $monto)
                    <li class="list-group-item d-flex justify-content-between bg-transparent"><span>{{ $categoria }}</span><strong>S/ {{ number_format($monto, 2) }}</strong></li>
                @empty
                    <li class="list-group-item text-muted bg-transparent">Sin gastos en el periodo.</li>
                @endforelse
            </ul>
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
                    <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i> Filtrar</button></div>
                </form>
                <div class="mt-2">Gastado en el periodo: <strong>S/ {{ number_format($total, 2) }}</strong></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="ps-3">Fecha</th><th>Gasto</th><th class="text-end">Monto</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($gastos as $gasto)
                        <tr class="{{ $gasto->anulado_en ? 'inactivo' : '' }}">
                            <td class="ps-3">{{ $gasto->fecha->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $gasto->categoria }}</span> {{ $gasto->descripcion }}
                                @if ($gasto->anulado_en)<span class="badge bg-secondary">Anulado</span>@endif
                            </td>
                            <td class="text-end">S/ {{ number_format($gasto->monto, 2) }}</td>
                            <td class="text-end pe-3">
                                @unless ($gasto->anulado_en)
                                    <form action="{{ route('gastos.destroy', $gasto) }}" method="POST" onsubmit="return confirm('¿Anular este gasto?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted p-4">Sin gastos en este periodo.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($gastos->hasPages())
                <div class="card-footer bg-white">{{ $gastos->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
