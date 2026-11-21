@extends('layouts.panel')

@section('titulo', 'Bitácora')
@section('icono', 'bi-journal-text')
@section('subtitulo', 'Lo que ocurre en la puerta y debe quedar anotado')

@section('contenido')
@php($puedeEscribir = auth()->user()->tieneRol(\App\Enums\Rol::Admin, \App\Enums\Rol::Guardia))
<div class="row g-3">
    @if ($puedeEscribir)
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Nueva anotación</div>
            <div class="card-body">
                <form action="{{ route('incidencias.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="titulo">Título *</label>
                        <input type="text" name="titulo" id="titulo" class="form-control" maxlength="150" required value="{{ old('titulo') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="descripcion">Detalle *</label>
                        <textarea name="descripcion" id="descripcion" class="form-control" rows="4" maxlength="3000" required>{{ old('descripcion') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="severidad">Severidad *</label>
                        <select name="severidad" id="severidad" class="form-select" required>
                            @foreach ($severidades as $severidad)
                                <option value="{{ $severidad->value }}" @selected(old('severidad') === $severidad->value)>{{ ucfirst($severidad->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Anotar</button>
                </form>
            </div>
        </div>
    </div>
    @endif
    <div class="{{ $puedeEscribir ? 'col-lg-8' : 'col-12' }}">
        @forelse ($incidencias as $incidencia)
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <h5 class="fw-bold mb-1">
                            <span class="badge {{ ['baja' => 'bg-secondary', 'media' => 'bg-warning text-dark', 'alta' => 'bg-danger'][$incidencia->severidad->value] }}">{{ ucfirst($incidencia->severidad->value) }}</span>
                            {{ $incidencia->titulo }}
                        </h5>
                        <small class="text-muted">{{ $incidencia->created_at->format('d/m/Y H:i') }} · {{ $incidencia->autor?->usuario ?? '—' }}</small>
                    </div>
                    <p class="mb-0" style="white-space: pre-line">{{ $incidencia->descripcion }}</p>
                </div>
            </div>
        @empty
            <div class="card"><p class="text-center text-muted p-4 mb-0">La bitácora está vacía.</p></div>
        @endforelse
        {{ $incidencias->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
