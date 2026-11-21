@extends('layouts.panel')

@section('titulo', 'Horarios')
@section('icono', 'bi-calendar-week')

@section('contenido')
@if (! $seccion)
    <div class="alert alert-warning">Primero crea grados y secciones.</div>
@else
<form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label class="form-label small mb-0" for="f_seccion">Aula</label>
        <select name="seccion_id" id="f_seccion" class="form-select" onchange="this.form.submit()">
            @foreach ($aulas as $aula)
                <option value="{{ $aula->id }}" @selected($seccion->id === $aula->id)>{{ $aula->nombreCompleto() }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Agregar bloque a {{ $seccion->nombreCompleto() }}</div>
            <div class="card-body">
                <form action="{{ route('horarios.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="seccion_id" value="{{ $seccion->id }}">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="dia">Día *</label>
                        <select name="dia" id="dia" class="form-select" required>
                            @foreach ($dias as $numero => $nombre)
                                <option value="{{ $numero }}" @selected((int) old('dia', 1) === $numero)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="hora_inicio">Desde *</label>
                            <input type="time" name="hora_inicio" id="hora_inicio" class="form-control" required value="{{ old('hora_inicio') }}">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="hora_fin">Hasta *</label>
                            <input type="time" name="hora_fin" id="hora_fin" class="form-control" required value="{{ old('hora_fin') }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="materia_id">Materia</label>
                        <select name="materia_id" id="materia_id" class="form-select">
                            <option value="">Sin materia (recreo, formación…)</option>
                            @foreach ($materias as $materia)
                                <option value="{{ $materia->id }}" @selected(old('materia_id') == $materia->id)>{{ $materia->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="titulo">Título (si no hay materia)</label>
                        <input type="text" name="titulo" id="titulo" class="form-control" maxlength="60" value="{{ old('titulo') }}" placeholder="Ej: Recreo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="personal_id">Docente</label>
                        <select name="personal_id" id="personal_id" class="form-select">
                            <option value="">Sin asignar</option>
                            @foreach ($docentes as $docente)
                                <option value="{{ $docente->id }}" @selected(old('personal_id') == $docente->id)>{{ $docente->apellidos }}, {{ $docente->nombres }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Agregar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="row g-3">
            @foreach ($dias as $numero => $nombre)
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">{{ $nombre }}</div>
                        <ul class="list-group list-group-flush">
                            @forelse ($porDia->get($numero, collect()) as $bloque)
                                <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                                    <div>
                                        <strong>{{ substr($bloque->hora_inicio, 0, 5) }}–{{ substr($bloque->hora_fin, 0, 5) }}</strong>
                                        {{ $bloque->nombre() }}
                                        @if ($bloque->personal)<br><small class="text-muted">{{ $bloque->personal->nombreCompleto() }}</small>@endif
                                    </div>
                                    <form action="{{ route('horarios.destroy', $bloque) }}" method="POST" onsubmit="return confirm('¿Quitar este bloque?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm text-danger border-0" title="Quitar bloque"><i class="bi bi-trash"></i></button>
                                    </form>
                                </li>
                            @empty
                                <li class="list-group-item text-muted small bg-transparent">Sin bloques.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif
@endsection
