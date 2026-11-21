@extends('layouts.base')

@section('titulo', $titulo)

@section('cuerpo')
<div class="barra-lote d-flex flex-wrap align-items-center gap-2 p-3 bg-white border-bottom sticky-top">
    <a href="{{ $dePersonal ? route('personal.index') : route('alumnos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    <div class="me-auto">
        <strong>{{ $titulo }}</strong>
        <div class="text-muted small">{{ $personas->count() }} carné(s), tamaño DNI. En cada fila: anverso y reverso. Caben 4 por hoja A4.</div>
    </div>
    <button type="button" class="btn btn-primary fw-bold" onclick="window.print()" @disabled($personas->isEmpty())><i class="bi bi-printer"></i> Imprimir todos</button>
</div>

@if ($personas->isEmpty())
    <p class="text-center text-muted p-5">No hay a quién imprimirle carné con ese filtro.</p>
@else
    <div class="carnes-lote">
        @foreach ($personas as $persona)
            @if ($dePersonal)
                @include('carnes.personal', ['persona' => $persona])
            @else
                @include('carnes.alumno', ['alumno' => $persona])
            @endif
        @endforeach
    </div>
@endif
@endsection
