@extends('layouts.panel')

@section('titulo', 'Seguridad')
@section('icono', 'bi-shield-lock-fill')
@section('subtitulo', 'Ingresos, intentos fallidos y cambios sensibles')

@section('contenido')
<div class="row g-3 mb-3">
    <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase fw-bold">Intentos fallidos hoy</div>
            <div class="fs-2 fw-bold {{ $fallosHoy > 20 ? 'text-danger' : '' }}">{{ $fallosHoy }}</div>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase fw-bold">Bloqueos hoy</div>
            <div class="fs-2 fw-bold {{ $bloqueosHoy > 0 ? 'text-danger' : '' }}">{{ $bloqueosHoy }}</div>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase fw-bold">Cuentas sin activar</div>
            <div class="fs-2 fw-bold {{ $sinActivar > 0 ? 'text-warning-emphasis' : '' }}">{{ $sinActivar }}</div>
            <div class="small text-muted">Siguen con la contraseña inicial.</div>
        </div></div>
    </div>
</div>

@if ($sinActivar > 0 && $conContrasenaInicialDebil)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>{{ $sinActivar }} cuenta(s) todavía usan la contraseña inicial "123456".</strong>
        Mientras no entren y creen la suya, cualquiera que conozca su usuario (el teléfono del apoderado o el correo) podría entrar antes que ellos.
        Pide a las familias y al personal que activen su cuenta cuanto antes.
    </div>
@endif

<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-0" for="evento">Mostrar</label>
                <select name="evento" id="evento" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todo</option>
                    @foreach ($eventos as $clave => $nombre)
                        <option value="{{ $clave }}" @selected(($filtros['evento'] ?? null) === $clave)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th class="ps-3">Cuándo</th><th>Qué pasó</th><th>Cuenta</th><th>Detalle</th><th>Desde (IP)</th></tr></thead>
            <tbody>
            @forelse ($registros as $registro)
                <tr>
                    <td class="ps-3 text-nowrap">{{ $registro->created_at->format('d/m/Y H:i') }}</td>
                    <td><span class="badge {{ $registro->esAlerta() ? 'bg-danger' : 'bg-light text-dark border' }}">{{ $registro->etiqueta() }}</span></td>
                    <td>{{ $registro->user?->usuario ?? '—' }}</td>
                    <td>{{ $registro->detalle }}</td>
                    <td class="text-muted">{{ $registro->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted p-4">Todavía no hay nada registrado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $registros->links('pagination::bootstrap-5') }}</div>
@endsection
