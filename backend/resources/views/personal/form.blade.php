@extends('layouts.panel')

@section('titulo', $persona->exists ? 'Editar trabajador' : 'Nuevo trabajador')

@section('icono', 'bi-person-badge')

@section('contenido')
<div class="card shadow-sm border-0" style="max-width: 760px">
    <div class="card-body">
        <form action="{{ $persona->exists ? route('personal.update', $persona) : route('personal.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if ($persona->exists) @method('PUT') @endif

            @if ($errors->any())
                <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
            @endif

            @include('partials.foto', ['persona' => $persona, 'ruta' => $persona->exists ? route('personal.foto', $persona) : null])

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="nombres">Nombres *</label>
                    <input type="text" name="nombres" id="nombres" class="form-control" maxlength="80" required value="{{ old('nombres', $persona->nombres) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="apellidos">Apellidos *</label>
                    <input type="text" name="apellidos" id="apellidos" class="form-control" maxlength="80" required value="{{ old('apellidos', $persona->apellidos) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="codigo">Código del carné *</label>
                    <input type="text" name="codigo" id="codigo" class="form-control" maxlength="20" required value="{{ old('codigo', $persona->codigo) }}" placeholder="Ej: DOC01">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="dni">DNI</label>
                    <input type="text" name="dni" id="dni" class="form-control" maxlength="15" value="{{ old('dni', $persona->dni) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="tipo">Tipo *</label>
                    <select name="tipo" id="tipo" class="form-select" required>
                        @foreach ($tipos as $tipo)
                            <option value="{{ $tipo->value }}" @selected(old('tipo', $persona->tipo?->value) === $tipo->value)>{{ ucfirst($tipo->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="cargo">Cargo</label>
                    <input type="text" name="cargo" id="cargo" class="form-control" maxlength="80" value="{{ old('cargo', $persona->cargo) }}" placeholder="Ej: Docente de Matemática">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="correo">Correo (su usuario en la app)</label>
                    <input type="email" name="correo" id="correo" class="form-control" maxlength="120" value="{{ old('correo', $persona->user?->usuario) }}">
                    <div class="form-text">Opcional. Con correo puede entrar a la app con la contraseña inicial.</div>
                </div>
                @if ($persona->exists)
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" @checked(old('activo', $persona->activo))>
                            <label class="form-check-label" for="activo">Trabajador activo</label>
                        </div>
                    </div>
                @endif
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary fw-bold">Guardar</button>
                <a href="{{ route('personal.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                @if ($persona->exists)
                    <a href="{{ route('personal.carne', $persona) }}" class="btn btn-outline-primary ms-auto"><i class="bi bi-person-vcard"></i> Ver carné</a>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
