@extends('layouts.panel')

@section('titulo', 'Carné de personal')
@section('icono', 'bi-person-vcard')
@section('subtitulo', $persona->nombreCompleto())

@section('acciones')
    <a href="{{ route('personal.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Personal</a>
    <a href="{{ route('personal.edit', $persona) }}" class="btn btn-outline-warning"><i class="bi bi-pencil-fill"></i> Editar y foto</a>
    <button type="button" class="btn btn-primary fw-bold" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
@endsection

@section('contenido')
    @include('carnes.personal', ['persona' => $persona])
    <p class="text-center text-muted mt-3">Se imprime a tamaño DNI (85,6 × 54 mm): anverso y reverso.</p>
@endsection
