@extends('layouts.panel')

@section('titulo', 'Asignación de docentes '.$anio)
@section('icono', 'bi-person-workspace')
@section('subtitulo', 'Quién dicta qué materia en qué aula. De esto depende lo que cada docente ve y califica en la app')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Nueva asignación</div>
            <div class="card-body">
                @if ($docentes->isEmpty())
                    <p class="text-muted mb-0">Primero registra personal de tipo "Docente" en <a href="{{ route('personal.index') }}">Personal</a>.</p>
                @else
                <form action="{{ route('asignaciones.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="anio" value="{{ $anio }}">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="personal_id">Docente *</label>
                        <select name="personal_id" id="personal_id" class="form-select" required>
                            <option value="">Elige…</option>
                            @foreach ($docentes as $docente)
                                <option value="{{ $docente->id }}" @selected(old('personal_id') == $docente->id)>{{ $docente->apellidos }}, {{ $docente->nombres }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="seccion_id">Aula *</label>
                        <select name="seccion_id" id="seccion_id" class="form-select" required>
                            <option value="">Elige…</option>
                            @foreach ($aulas as $aula)
                                <option value="{{ $aula->id }}" @selected(old('seccion_id') == $aula->id)>{{ $aula->nombreCompleto() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="materia_id">Materia *</label>
                        <select name="materia_id" id="materia_id" class="form-select" required>
                            <option value="">Elige…</option>
                            @foreach ($materias as $materia)
                                <option value="{{ $materia->id }}" @selected(old('materia_id') == $materia->id)>{{ $materia->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Asignar</button>
                </form>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        @forelse ($porDocente as $asignaciones)
            @php($docente = $asignaciones->first()->personal)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <span><i class="bi bi-person-badge text-secondary"></i> {{ $docente->apellidos }}, {{ $docente->nombres }}</span>
                    @unless ($docente->user_id)<small class="text-danger fw-normal">Sin cuenta: agrégale un correo para que entre a la app</small>@endunless
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($asignaciones->sortBy(fn ($a) => $a->seccion->nombreCompleto().$a->materia->nombre) as $asignacion)
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                            <span><strong>{{ $asignacion->materia->nombre }}</strong> · {{ $asignacion->seccion->nombreCompleto() }}</span>
                            <form action="{{ route('asignaciones.destroy', $asignacion) }}" method="POST" onsubmit="return confirm('¿Quitar esta asignación?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm text-danger border-0" title="Quitar"><i class="bi bi-trash"></i></button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="card"><p class="text-center text-muted p-4 mb-0">Todavía no hay docentes asignados en {{ $anio }}.</p></div>
        @endforelse
    </div>
</div>
@endsection
