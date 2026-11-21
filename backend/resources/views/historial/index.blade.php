@extends('layouts.panel')

@section('titulo', 'Historial de asistencia')
@section('icono', 'bi-clock-history')

@section('acciones')
    <a href="{{ route('historial.exportar', request()->query()) }}" class="btn btn-success fw-bold"><i class="bi bi-file-earmark-excel"></i> Descargar Excel</a>
@endsection

@section('contenido')
<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-0" for="tipo">Quiénes</label>
                <select name="tipo" id="tipo" class="form-select form-select-sm">
                    <option value="alumno" @selected($filtros['tipo'] === 'alumno')>Alumnos</option>
                    <option value="personal" @selected($filtros['tipo'] === 'personal')>Personal</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="desde">Desde</label>
                <input type="date" name="desde" id="desde" class="form-control form-control-sm" value="{{ $filtros['desde'] }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="hasta">Hasta</label>
                <input type="date" name="hasta" id="hasta" class="form-control form-control-sm" value="{{ $filtros['hasta'] }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-0" for="q">Nombre o código</label>
                <input type="search" name="q" id="q" class="form-control form-control-sm" value="{{ $filtros['q'] }}">
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-funnel"></i> Filtrar</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th class="ps-3">Fecha</th><th>Persona</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse ($registros as $registro)
                <tr>
                    <td class="ps-3">{{ $registro->fecha->format('d/m/Y') }}</td>
                    <td>
                        <div class="fw-bold">{{ $registro->persona->apellidos }}, {{ $registro->persona->nombres }}</div>
                        <small class="text-muted">
                            {{ $registro->persona->codigo }} ·
                            {{ $registro->persona instanceof \App\Models\Alumno ? ($registro->persona->aula() ?? 'Sin aula') : ($registro->persona->cargo ?? 'Personal') }}
                        </small>
                    </td>
                    <td>{{ \App\Models\Asistencia::horaCorta($registro->hora_entrada) }}</td>
                    <td>{{ \App\Models\Asistencia::horaCorta($registro->hora_salida) ?? '—' }}</td>
                    <td>
                        <span class="badge {{ $registro->estado === \App\Enums\EstadoAsistencia::Tarde ? 'bg-warning text-dark' : 'bg-success' }}">{{ ucfirst($registro->estado->value) }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted p-4">No hay registros con estos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($registros->hasPages())
        <div class="card-footer bg-white">{{ $registros->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
