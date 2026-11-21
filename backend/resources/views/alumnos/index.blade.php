@extends('layouts.panel')

@section('titulo', 'Alumnos')
@section('icono', 'bi-mortarboard')

@section('acciones')
    <a href="{{ route('carnes.lote', array_filter(['seccion_id' => $filtros['seccion_id'] ?? null])) }}" class="btn btn-outline-primary" target="_blank" rel="noopener">
        <i class="bi bi-printer"></i> {{ empty($filtros['seccion_id']) ? 'Imprimir todos los carnés' : 'Imprimir carnés de esta aula' }}
    </a>
    <a href="{{ route('alumnos.create') }}" class="btn btn-primary fw-bold"><i class="bi bi-plus-lg"></i> Nuevo alumno</a>
@endsection

@section('contenido')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <div class="row g-2 align-items-center">
            <form method="GET" class="col-12 row g-2">
                <div class="col-sm-5">
                    <input type="search" name="q" class="form-control" placeholder="Buscar por nombre o código"
                           value="{{ $filtros['q'] ?? '' }}" aria-label="Buscar alumnos">
                </div>
                <div class="col-sm-5">
                    <select name="seccion_id" class="form-select" aria-label="Filtrar por aula">
                        <option value="">Todas las aulas</option>
                        @foreach ($aulas as $aula)
                            <option value="{{ $aula->id }}" @selected(($filtros['seccion_id'] ?? null) == $aula->id)>{{ $aula->nombreCompleto() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-2"><button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Alumno</th><th>Aula</th><th>Cuenta</th><th class="text-end pe-3">Acciones</th></tr></thead>
            <tbody>
            @forelse ($alumnos as $alumno)
                <tr class="{{ $alumno->activo ? '' : 'inactivo' }}">
                    <td class="ps-3">
                        <div class="fw-bold">{{ $alumno->apellidos }}, {{ $alumno->nombres }}</div>
                        <small class="text-muted">{{ $alumno->codigo }}@if ($alumno->dni) · DNI {{ $alumno->dni }}@endif</small>
                    </td>
                    <td>{{ $alumno->aula() ?? 'Sin aula' }}</td>
                    <td>
                        @if ($alumno->user)
                            <small>{{ $alumno->user->usuario }}</small>
                        @else
                            <small class="text-muted">Sin cuenta</small>
                        @endif
                    </td>
                    <td class="text-end pe-3" style="white-space: nowrap">
                        @unless ($alumno->activo)<span class="badge bg-secondary me-1">De baja</span>@endunless
                        <a href="{{ route('alumnos.carne', $alumno) }}" class="btn btn-sm btn-outline-primary" title="Ver carné"><i class="bi bi-person-vcard"></i></a>
                        <a href="{{ route('alumnos.edit', $alumno) }}" class="btn btn-sm btn-outline-warning" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                        @if ($alumno->activo)
                            <form action="{{ route('alumnos.destroy', $alumno) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Dar de baja a este alumno? Su historial se conserva.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Dar de baja"><i class="bi bi-person-dash-fill"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted p-4">No hay alumnos que mostrar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($alumnos->hasPages())
        <div class="card-footer bg-white">{{ $alumnos->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
