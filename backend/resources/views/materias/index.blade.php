@extends('layouts.panel')

@section('titulo', 'Materias')
@section('icono', 'bi-book-fill')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Nueva materia</div>
            <div class="card-body">
                <form action="{{ route('materias.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="nombre">Nombre *</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="100" required value="{{ old('nombre') }}" placeholder="Ej: Matemática">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="nivel">Nivel</label>
                        <select name="nivel" id="nivel" class="form-select">
                            <option value="">Todos los niveles</option>
                            @foreach ($niveles as $nivel)
                                <option value="{{ $nivel->value }}" @selected(old('nivel') === $nivel->value)>Solo {{ $nivel->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Agregar materia</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th class="ps-3">Materia y nivel</th><th class="text-end pe-3"></th></tr></thead>
                    <tbody>
                    @forelse ($materias as $materia)
                        <tr class="{{ $materia->activo ? '' : 'inactivo' }}">
                            <td class="ps-3">
                                <form action="{{ route('materias.update', $materia) }}" method="POST" class="row g-1">
                                    @csrf @method('PUT')
                                    <div class="col-sm-6"><input type="text" name="nombre" class="form-control form-control-sm" value="{{ $materia->nombre }}" maxlength="100" required aria-label="Nombre"></div>
                                    <div class="col-sm-4">
                                        <select name="nivel" class="form-select form-select-sm" aria-label="Nivel">
                                            <option value="">Todos los niveles</option>
                                            @foreach ($niveles as $nivel)
                                                <option value="{{ $nivel->value }}" @selected($materia->nivel === $nivel)>Solo {{ $nivel->etiqueta() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-2"><button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Guardar"><i class="bi bi-check-lg"></i></button></div>
                                </form>
                            </td>
                            <td class="text-end pe-3">
                                <form action="{{ route('materias.alternar', $materia) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $materia->activo ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                            title="{{ $materia->activo ? 'Desactivar' : 'Reactivar' }}"><i class="bi bi-power"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted p-4">Sin materias registradas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
