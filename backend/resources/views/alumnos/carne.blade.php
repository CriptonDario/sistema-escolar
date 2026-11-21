@extends('layouts.panel')

@section('titulo', 'Carné de alumno')
@section('icono', 'bi-person-vcard')
@section('subtitulo', $alumno->nombreCompleto())

@section('acciones')
    <a href="{{ route('alumnos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Alumnos</a>
    <a href="{{ route('alumnos.edit', $alumno) }}" class="btn btn-outline-warning"><i class="bi bi-pencil-fill"></i> Editar y foto</a>
    <button type="button" class="btn btn-primary fw-bold" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
@endsection

@section('contenido')
    @include('carnes.alumno', ['alumno' => $alumno])
    <p class="text-center text-muted mt-3">Se imprime a tamaño DNI (85,6 × 54 mm): anverso y reverso.</p>
@endsection
