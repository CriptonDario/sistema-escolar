@extends('layouts.panel')

@section('titulo', $alumno->exists ? 'Editar alumno' : 'Nuevo alumno')

@section('icono', 'bi-mortarboard')

@section('contenido')
<div class="card shadow-sm border-0" style="max-width: 760px">
    <div class="card-body">
        <form action="{{ $alumno->exists ? route('alumnos.update', $alumno) : route('alumnos.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if ($alumno->exists) @method('PUT') @endif

            @if ($errors->any())
                <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
            @endif

            @include('partials.foto', ['persona' => $alumno, 'ruta' => $alumno->exists ? route('alumnos.foto', $alumno) : null])

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="nombres">Nombres *</label>
                    <input type="text" name="nombres" id="nombres" class="form-control" maxlength="80" required value="{{ old('nombres', $alumno->nombres) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="apellidos">Apellidos *</label>
                    <input type="text" name="apellidos" id="apellidos" class="form-control" maxlength="80" required value="{{ old('apellidos', $alumno->apellidos) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="codigo">Código del carné *</label>
                    <input type="text" name="codigo" id="codigo" class="form-control" maxlength="20" required value="{{ old('codigo', $alumno->codigo) }}" placeholder="Ej: 2SB01">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="dni">DNI</label>
                    <input type="text" name="dni" id="dni" class="form-control" maxlength="15" value="{{ old('dni', $alumno->dni) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="fecha_nacimiento">Fecha de nacimiento</label>
                    <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control"
                           value="{{ old('fecha_nacimiento', $alumno->fecha_nacimiento?->toDateString()) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="seccion_id">Aula</label>
                    <select name="seccion_id" id="seccion_id" class="form-select">
                        <option value="">Sin aula</option>
                        @foreach ($aulas as $aula)
                            <option value="{{ $aula->id }}" @selected(old('seccion_id', $alumno->seccion_id) == $aula->id)>{{ $aula->nombreCompleto() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="correo">Correo (su usuario en la app)</label>
                    <input type="email" name="correo" id="correo" class="form-control" maxlength="120" value="{{ old('correo', $alumno->user?->usuario) }}">
                    <div class="form-text">Opcional. Con correo, el alumno puede entrar a la app con la contraseña inicial.</div>
                </div>
                @if ($alumno->exists)
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" @checked(old('activo', $alumno->activo))>
                            <label class="form-check-label" for="activo">Alumno activo</label>
                        </div>
                    </div>
                @endif
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary fw-bold">Guardar</button>
                <a href="{{ route('alumnos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                @if ($alumno->exists)
                    <a href="{{ route('alumnos.carne', $alumno) }}" class="btn btn-outline-primary ms-auto"><i class="bi bi-person-vcard"></i> Ver carné</a>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection

