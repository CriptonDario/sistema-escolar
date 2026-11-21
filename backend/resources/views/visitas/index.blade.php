@extends('layouts.panel')

@section('titulo', 'Visitantes')
@section('icono', 'bi-person-vcard')
@section('subtitulo', $adentro.' visitante(s) dentro del colegio en este momento')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-box-arrow-in-right"></i> Registrar ingreso</div>
            <div class="card-body">
                <form action="{{ route('visitas.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="nombre">Nombre completo *</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" required value="{{ old('nombre') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="documento">DNI</label>
                        <input type="text" name="documento" id="documento" class="form-control" maxlength="20" value="{{ old('documento') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="motivo">Motivo *</label>
                        <input type="text" name="motivo" id="motivo" class="form-control" maxlength="255" required value="{{ old('motivo') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="visita_a">A quién visita</label>
                        <input type="text" name="visita_a" id="visita_a" class="form-control" maxlength="120" value="{{ old('visita_a') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="pase">Pase que se le entrega</label>
                        <select name="pase" id="pase" class="form-select">
                            <option value="">Sin pase</option>
                            @foreach ($pasesLibres as $codigo)
                                <option value="{{ $codigo }}" @selected(old('pase') === $codigo)>{{ $codigo }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ count($pasesLibres) }} de {{ $totalDePases }} pases libres.</div>
                        @error('pase')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Registrar ingreso</button>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-person-vcard"></i> Pases de visitante</div>
            <div class="card-body">
                <form action="{{ route('visitas.pases.devolver') }}" method="POST" class="mb-3">
                    @csrf
                    <label class="form-label fw-bold" for="codigo">Devolver un pase</label>
                    <div class="input-group">
                        <input type="text" name="codigo" id="codigo" class="form-control text-uppercase" maxlength="10" required placeholder="VIS-01" autocomplete="off">
                        <button type="submit" class="btn btn-outline-primary">Marcar salida</button>
                    </div>
                    <div class="form-text">También puedes escanear el pase en el Kiosco o en la app: marca la salida solo.</div>
                </form>

                <a href="{{ route('visitas.pases') }}" class="btn btn-outline-secondary w-100" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Imprimir los {{ $totalDePases }} pases</a>

                @if (auth()->user()->rol === \App\Enums\Rol::Admin)
                    <form action="{{ route('visitas.pases.fijar') }}" method="POST" class="row g-2 align-items-end mt-2">
                        @csrf @method('PUT')
                        <div class="col">
                            <label class="form-label small mb-0" for="cantidad">Cantidad de pases del colegio</label>
                            <input type="number" name="cantidad" id="cantidad" class="form-control form-control-sm" min="1" max="99" required value="{{ old('cantidad', $totalDePases) }}">
                        </div>
                        <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button></div>
                    </form>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label small mb-0" for="fecha">Día</label>
                        <input type="date" name="fecha" id="fecha" class="form-control form-control-sm" value="{{ $fecha }}" onchange="this.form.submit()">
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="ps-3">Visitante</th><th>Motivo</th><th>Entrada</th><th>Salida</th></tr></thead>
                    <tbody>
                    @forelse ($visitas as $visita)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold">{{ $visita->nombre }}</div>
                                @if ($visita->documento)<small class="text-muted">DNI {{ $visita->documento }}</small>@endif
                                @if ($visita->pase)<span class="badge {{ $visita->sigueAdentro() ? 'bg-primary' : 'bg-secondary' }}">{{ $visita->pase }}</span>@endif
                            </td>
                            <td>{{ $visita->motivo }}@if ($visita->visita_a)<br><small class="text-muted">Visita a: {{ $visita->visita_a }}</small>@endif</td>
                            <td>{{ $visita->entrada->format('H:i') }}</td>
                            <td>
                                @if ($visita->sigueAdentro())
                                    <form action="{{ route('visitas.salida', $visita) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Marcar salida</button>
                                    </form>
                                @else
                                    {{ $visita->salida->format('H:i') }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted p-4">Sin visitas ese día.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
