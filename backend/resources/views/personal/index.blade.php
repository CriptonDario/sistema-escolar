@extends('layouts.panel')

@section('titulo', 'Personal')
@section('icono', 'bi-person-badge')

@section('acciones')
    <a href="{{ route('carnes.lote', ['de' => 'personal']) }}" class="btn btn-outline-primary" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Imprimir todos los carnés</a>
    <a href="{{ route('personal.create') }}" class="btn btn-primary fw-bold"><i class="bi bi-plus-lg"></i> Nuevo trabajador</a>
@endsection

@section('contenido')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <div class="row g-2 align-items-center">
            <form method="GET" class="col-12 row g-2">
                <div class="col-sm-8">
                    <input type="search" name="q" class="form-control" placeholder="Buscar por nombre o código"
                           value="{{ $filtros['q'] ?? '' }}" aria-label="Buscar personal">
                </div>
                <div class="col-sm-2"><button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Trabajador</th><th>Cargo</th><th>Cuenta</th><th class="text-end pe-3">Acciones</th></tr></thead>
            <tbody>
            @forelse ($personal as $persona)
                <tr class="{{ $persona->activo ? '' : 'inactivo' }}">
                    <td class="ps-3">
                        <div class="fw-bold">{{ $persona->apellidos }}, {{ $persona->nombres }}</div>
                        <small class="text-muted">{{ $persona->codigo }}</small>
                    </td>
                    <td>{{ $persona->cargo ?? '—' }} <span class="badge bg-light text-dark border">{{ ucfirst($persona->tipo->value) }}</span></td>
                    <td>
                        @if ($persona->user)
                            <small>{{ $persona->user->usuario }}</small>
                        @else
                            <small class="text-muted">Sin cuenta</small>
                        @endif
                    </td>
                    <td class="text-end pe-3" style="white-space: nowrap">
                        @unless ($persona->activo)<span class="badge bg-secondary me-1">De baja</span>@endunless
                        <a href="{{ route('personal.carne', $persona) }}" class="btn btn-sm btn-outline-primary" title="Ver carné"><i class="bi bi-person-vcard"></i></a>
                        <a href="{{ route('personal.edit', $persona) }}" class="btn btn-sm btn-outline-warning" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                        @if ($persona->activo)
                            <form action="{{ route('personal.destroy', $persona) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Dar de baja a este trabajador? Su historial se conserva.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Dar de baja"><i class="bi bi-person-dash-fill"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted p-4">No hay personal que mostrar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($personal->hasPages())
        <div class="card-footer bg-white">{{ $personal->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
