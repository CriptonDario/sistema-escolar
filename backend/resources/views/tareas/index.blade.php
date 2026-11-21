@extends('layouts.panel')

@section('titulo', 'Tareas y agenda')
@section('icono', 'bi-list-task')
@section('subtitulo', 'Tareas, exámenes y actividades por aula; los apoderados reciben el aviso en la app')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Publicar</div>
            <div class="card-body">
                <form action="{{ route('tareas.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="seccion_id">Aula *</label>
                        <select name="seccion_id" id="seccion_id" class="form-select" required>
                            <option value="">Elige el aula…</option>
                            @foreach ($aulas as $aula)
                                <option value="{{ $aula->id }}" @selected(old('seccion_id') == $aula->id)>{{ $aula->nombreCompleto() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-5 mb-3">
                            <label class="form-label fw-bold" for="tipo">Tipo *</label>
                            <select name="tipo" id="tipo" class="form-select" required>
                                @foreach ($tipos as $tipo)
                                    <option value="{{ $tipo->value }}" @selected(old('tipo') === $tipo->value)>{{ ucfirst($tipo->value) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-7 mb-3">
                            <label class="form-label fw-bold" for="materia_id">Materia</label>
                            <select name="materia_id" id="materia_id" class="form-select">
                                <option value="">—</option>
                                @foreach ($materias as $materia)
                                    <option value="{{ $materia->id }}" @selected(old('materia_id') == $materia->id)>{{ $materia->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="titulo">Título *</label>
                        <input type="text" name="titulo" id="titulo" class="form-control" maxlength="120" required value="{{ old('titulo') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="descripcion">Detalle</label>
                        <textarea name="descripcion" id="descripcion" class="form-control" rows="3" maxlength="2000">{{ old('descripcion') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="fecha_entrega">Fecha *</label>
                        <input type="date" name="fecha_entrega" id="fecha_entrega" class="form-control" required value="{{ old('fecha_entrega') }}">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Publicar y avisar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <form method="GET" class="row g-2">
                    <div class="col">
                        <select name="seccion_id" class="form-select form-select-sm" aria-label="Filtrar por aula" onchange="this.form.submit()">
                            <option value="">Todas las aulas</option>
                            @foreach ($aulas as $aula)
                                <option value="{{ $aula->id }}" @selected(($filtros['seccion_id'] ?? null) == $aula->id)>{{ $aula->nombreCompleto() }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="ps-3">Fecha</th><th>Aula</th><th>Publicación</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($tareas as $tarea)
                        <tr class="{{ $tarea->anulado_en ? 'inactivo' : '' }}">
                            <td class="ps-3" style="white-space: nowrap">{{ $tarea->fecha_entrega->format('d/m/Y') }}</td>
                            <td>{{ $tarea->seccion->nombreCompleto() }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ ucfirst($tarea->tipo->value) }}</span>
                                <strong>{{ $tarea->titulo }}</strong>
                                @if ($tarea->materia)<small class="text-muted">· {{ $tarea->materia->nombre }}</small>@endif
                                @if ($tarea->anulado_en)<span class="badge bg-secondary">Anulada</span>@endif
                                @if ($tarea->descripcion)<br><small class="text-muted">{{ $tarea->descripcion }}</small>@endif
                            </td>
                            <td class="text-end pe-3">
                                @unless ($tarea->anulado_en)
                                    <form action="{{ route('tareas.destroy', $tarea) }}" method="POST" onsubmit="return confirm('¿Anular esta publicación?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted p-4">Sin publicaciones.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($tareas->hasPages())
                <div class="card-footer bg-white">{{ $tareas->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
