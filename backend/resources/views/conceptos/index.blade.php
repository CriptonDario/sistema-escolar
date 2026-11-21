@extends('layouts.panel')

@section('titulo', 'Conceptos de pago')
@section('icono', 'bi-tags-fill')
@section('subtitulo', 'Lo que el colegio cobra: matrícula, pensión y otros')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Nuevo concepto</div>
            <div class="card-body">
                <form action="{{ route('conceptos.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="nombre">Nombre *</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="100" required value="{{ old('nombre') }}" placeholder="Ej: Pensión">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="tipo">Tipo *</label>
                            <select name="tipo" id="tipo" class="form-select" required>
                                @foreach ($tipos as $tipo)
                                    <option value="{{ $tipo->value }}" @selected(old('tipo') === $tipo->value)>{{ $tipo->etiqueta() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="monto">Monto (S/) *</label>
                            <input type="number" name="monto" id="monto" class="form-control" min="0" step="0.01" required value="{{ old('monto', '0.00') }}">
                        </div>
                    </div>
                    <div class="form-text mb-3">El tipo "Pensión" se cobra por mes y aparece en el estado de cuenta.</div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Agregar concepto</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th class="ps-3">Concepto, tipo y monto</th><th class="text-end pe-3"></th></tr></thead>
                    <tbody>
                    @forelse ($conceptos as $concepto)
                        <tr class="{{ $concepto->activo ? '' : 'inactivo' }}">
                            <td class="ps-3">
                                <form action="{{ route('conceptos.update', $concepto) }}" method="POST" class="row g-1">
                                    @csrf @method('PUT')
                                    <div class="col-sm-5"><input type="text" name="nombre" class="form-control form-control-sm" value="{{ $concepto->nombre }}" maxlength="100" required aria-label="Nombre"></div>
                                    <div class="col-sm-3">
                                        <select name="tipo" class="form-select form-select-sm" aria-label="Tipo">
                                            @foreach ($tipos as $tipo)
                                                <option value="{{ $tipo->value }}" @selected($concepto->tipo === $tipo)>{{ $tipo->etiqueta() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-2"><input type="number" name="monto" class="form-control form-control-sm" value="{{ number_format($concepto->monto, 2, '.', '') }}" min="0" step="0.01" required aria-label="Monto"></div>
                                    <div class="col-sm-2"><button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Guardar"><i class="bi bi-check-lg"></i></button></div>
                                </form>
                            </td>
                            <td class="text-end pe-3">
                                <form action="{{ route('conceptos.alternar', $concepto) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $concepto->activo ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                            title="{{ $concepto->activo ? 'Desactivar' : 'Reactivar' }}"><i class="bi bi-power"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted p-4">Crea al menos un concepto para poder registrar pagos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
