@extends('layouts.panel')

@section('titulo', 'Inventario')
@section('icono', 'bi-box-seam-fill')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Nuevo producto</div>
            <div class="card-body">
                <form action="{{ route('inventario.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="nombre">Nombre *</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="100" required value="{{ old('nombre') }}">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="unidad">Unidad *</label>
                            <input type="text" name="unidad" id="unidad" class="form-control" maxlength="20" required value="{{ old('unidad', 'unidad') }}">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="stock_minimo">Stock mínimo *</label>
                            <input type="number" name="stock_minimo" id="stock_minimo" class="form-control" min="0" required value="{{ old('stock_minimo', 0) }}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Crear producto</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th class="ps-3">Producto</th><th>Stock</th><th>Entrada o salida</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($productos as $producto)
                        <tr class="{{ $producto->activo ? '' : 'inactivo' }}">
                            <td class="ps-3 fw-bold">{{ $producto->nombre }}</td>
                            <td>
                                <span class="fw-bold {{ $producto->stockBajo() ? 'text-danger' : '' }}">{{ $producto->stock }}</span> {{ $producto->unidad }}
                                @if ($producto->stockBajo())<br><small class="text-danger">Stock bajo (mín. {{ $producto->stock_minimo }})</small>@endif
                            </td>
                            <td>
                                @if ($producto->activo)
                                <form action="{{ route('inventario.mover', $producto) }}" method="POST" class="row g-1">
                                    @csrf
                                    <div class="col-3">
                                        <select name="tipo" class="form-select form-select-sm" aria-label="Tipo de movimiento">
                                            <option value="entrada">Entrada</option>
                                            <option value="salida">Salida</option>
                                        </select>
                                    </div>
                                    <div class="col-2"><input type="number" name="cantidad" class="form-control form-control-sm" min="1" required placeholder="Cant." aria-label="Cantidad"></div>
                                    <div class="col-5"><input type="text" name="motivo" class="form-control form-control-sm" maxlength="255" required placeholder="Motivo" aria-label="Motivo"></div>
                                    <div class="col-2"><button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Registrar movimiento"><i class="bi bi-check-lg"></i></button></div>
                                </form>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <form action="{{ route('inventario.alternar', $producto) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $producto->activo ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                            title="{{ $producto->activo ? 'Desactivar' : 'Reactivar' }}"><i class="bi bi-power"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted p-4">Sin productos registrados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Últimos movimientos</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                    @forelse ($movimientos as $movimiento)
                        <tr>
                            <td class="ps-3" style="white-space: nowrap">{{ $movimiento->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $movimiento->producto->nombre }}</td>
                            <td><span class="badge {{ $movimiento->tipo === 'entrada' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($movimiento->tipo) }} {{ $movimiento->cantidad }}</span></td>
                            <td class="pe-3">{{ $movimiento->motivo }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted p-4">Todavía no hay movimientos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
