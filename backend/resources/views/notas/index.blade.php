@extends('layouts.panel')

@section('titulo', 'Notas')
@section('icono', 'bi-journal-check')
@section('subtitulo', 'Elige el aula, la materia y el periodo, y registra las notas de la lista')

@section('contenido')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-0" for="anio">Año</label>
                <input type="number" name="anio" id="anio" class="form-control" value="{{ $anio }}" min="2000" max="2100">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0" for="seccion_id">Aula</label>
                <select name="seccion_id" id="seccion_id" class="form-select" required onchange="this.form.submit()">
                    <option value="">Elige el aula…</option>
                    @foreach ($aulas as $aula)
                        <option value="{{ $aula->id }}" @selected($seccion?->id === $aula->id)>{{ $aula->nombreCompleto() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0" for="materia_id">Materia</label>
                <select name="materia_id" id="materia_id" class="form-select" required>
                    <option value="">Elige la materia…</option>
                    @foreach ($materias as $opcion)
                        <option value="{{ $opcion->id }}" @selected($materia?->id === $opcion->id)>{{ $opcion->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="periodo">Periodo</label>
                <select name="periodo" id="periodo" class="form-select">
                    @foreach ($periodos as $numero => $etiqueta)
                        <option value="{{ $numero }}" @selected($periodo === $numero)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Ver lista</button></div>
        </form>
    </div>
</div>

@if ($seccion && $materia)
    <div class="card">
        <div class="card-header d-flex justify-content-between flex-wrap gap-2">
            <span>{{ $materia->nombre }} — {{ $seccion->nombreCompleto() }} — {{ $periodos[$periodo] }} {{ $anio }}</span>
            <span class="badge bg-light text-dark border fw-normal">Escala {{ mb_strtolower($escala->etiqueta()) }}</span>
        </div>
        @if ($alumnos->isEmpty())
            <p class="text-center text-muted p-4 mb-0">Esta aula no tiene alumnos activos.</p>
        @else
            <form action="{{ route('notas.guardar') }}" method="POST">
                @csrf
                <input type="hidden" name="anio" value="{{ $anio }}">
                <input type="hidden" name="seccion_id" value="{{ $seccion->id }}">
                <input type="hidden" name="materia_id" value="{{ $materia->id }}">
                <input type="hidden" name="periodo" value="{{ $periodo }}">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th class="ps-3" style="width: 50px">N.º</th><th>Alumno</th><th style="width: 220px">{{ $escala === \App\Enums\EscalaCalificacion::Literal ? 'Calificación' : 'Nota (0–'.$notaMaxima.')' }}</th></tr></thead>
                        <tbody>
                        @foreach ($alumnos as $alumno)
                            <tr>
                                <td class="ps-3 text-muted">{{ $loop->iteration }}</td>
                                <td><label for="nota_{{ $alumno->id }}" class="mb-0">{{ $alumno->apellidos }}, {{ $alumno->nombres }}</label></td>
                                <td>
                                    @php($actual = old('notas.'.$alumno->id, $notas->get($alumno->id)?->valor()))
                                    @if ($escala === \App\Enums\EscalaCalificacion::Literal)
                                        <select name="notas[{{ $alumno->id }}]" id="nota_{{ $alumno->id }}" class="form-select form-select-sm">
                                            <option value="">Sin calificar</option>
                                            @foreach ($letras as $letra => $significado)
                                                <option value="{{ $letra }}" @selected($actual === $letra)>{{ $letra }} — {{ $significado }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="number" name="notas[{{ $alumno->id }}]" id="nota_{{ $alumno->id }}" class="form-control form-control-sm"
                                               min="0" max="{{ $notaMaxima }}" step="0.1" inputmode="decimal" value="{{ is_numeric($actual) ? $actual : '' }}">
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                    <small class="text-muted">Deja una calificación vacía para borrarla.</small>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-check-lg"></i> Guardar notas</button>
                </div>
            </form>
        @endif
    </div>
@endif
@endsection
