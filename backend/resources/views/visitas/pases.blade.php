@extends('layouts.base')

@section('titulo', 'Pases de visitante')

@section('cuerpo')
<div class="barra-lote d-flex flex-wrap align-items-center gap-2 p-3 bg-white border-bottom sticky-top">
    <a href="{{ route('visitas.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Visitantes</a>
    <div class="me-auto">
        <strong>Pases de visitante</strong>
        <div class="text-muted small">{{ count($codigos) }} pase(s), tamaño DNI. En cada fila: anverso y reverso. Caben 4 por hoja A4.</div>
    </div>
    <button type="button" class="btn btn-primary fw-bold" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir todos</button>
</div>

<div class="carnes-lote">
    @foreach ($codigos as $codigo)
        @include('carnes.visitante', ['codigo' => $codigo])
    @endforeach
</div>
@endsection
