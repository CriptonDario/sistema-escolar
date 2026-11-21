@extends('layouts.panel')

@section('titulo', 'Usuarios del sistema')
@section('icono', 'bi-person-gear')
@section('subtitulo', 'Cuentas que entran a este panel: administrador, guardia y dirección')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Nueva cuenta</div>
            <div class="card-body">
                <form action="{{ route('usuarios.store') }}" method="POST" autocomplete="off">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="nombre">Nombre *</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" required value="{{ old('nombre') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="usuario">Usuario *</label>
                        <input type="text" name="usuario" id="usuario" class="form-control" maxlength="120" required value="{{ old('usuario') }}" autocapitalize="none">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="rol">Rol *</label>
                        <select name="rol" id="rol" class="form-select" required>
                            @foreach ($roles as $rol)
                                <option value="{{ $rol->value }}" @selected(old('rol', 'guardia') === $rol->value)>{{ ucfirst($rol->value) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Guardia: solo el kiosco. Dirección: consulta de asistencia.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="clave">Contraseña *</label>
                        <input type="password" name="clave" id="clave" class="form-control" maxlength="100" required autocomplete="new-password">
                        <div class="form-text">Mínimo 8 caracteres, con mayúsculas, minúsculas y números.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Crear cuenta</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th class="ps-3">Cuenta</th><th>Rol y nombre</th><th>Nueva contraseña</th><th class="text-end pe-3"></th></tr></thead>
                    <tbody>
                    @foreach ($usuarios as $usuario)
                        <tr class="{{ $usuario->activo ? '' : 'inactivo' }}">
                            <td class="ps-3">
                                <div class="fw-bold">{{ $usuario->usuario }}</div>
                                @if ($usuario->is(auth()->user()))<span class="badge bg-info text-dark">Tú</span>@endif
                                @unless ($usuario->activo)<span class="badge bg-secondary">Inactiva</span>@endunless
                            </td>
                            <td colspan="2">
                                <form action="{{ route('usuarios.update', $usuario) }}" method="POST" class="row g-1" autocomplete="off">
                                    @csrf @method('PUT')
                                    <div class="col-sm-4">
                                        <input type="text" name="nombre" class="form-control form-control-sm" value="{{ $usuario->nombre }}" maxlength="120" required aria-label="Nombre">
                                    </div>
                                    <div class="col-sm-3">
                                        <select name="rol" class="form-select form-select-sm" aria-label="Rol">
                                            @foreach ($roles as $rol)
                                                <option value="{{ $rol->value }}" @selected($usuario->rol === $rol)>{{ ucfirst($rol->value) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-3">
                                        <input type="password" name="clave" class="form-control form-control-sm" placeholder="Sin cambios" maxlength="100" autocomplete="new-password" aria-label="Nueva contraseña">
                                    </div>
                                    <div class="col-sm-2">
                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Guardar cambios"><i class="bi bi-check-lg"></i></button>
                                    </div>
                                </form>
                            </td>
                            <td class="text-end pe-3">
                                @unless ($usuario->is(auth()->user()))
                                    <form action="{{ route('usuarios.alternar', $usuario) }}" method="POST"
                                          onsubmit="return confirm('{{ $usuario->activo ? '¿Desactivar esta cuenta?' : '¿Reactivar esta cuenta?' }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm {{ $usuario->activo ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                                title="{{ $usuario->activo ? 'Desactivar' : 'Reactivar' }}"><i class="bi bi-power"></i></button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
