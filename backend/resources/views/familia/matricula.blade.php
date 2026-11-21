@extends('layouts.familia')

@section('titulo', 'Matrícula '.$resumen['anio'])

@section('contenido')
<a href="{{ route('familia.inicio') }}" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Inicio</a>
<h1 class="h4 fw-bold mt-1">Matrícula {{ $resumen['anio'] }}</h1>

@if ($resumen['abierta'])
    <div class="alert alert-info">
        <strong>La matrícula en línea está abierta.</strong>
        Costo: <strong>S/ {{ number_format($resumen['monto'], 2) }}</strong> por alumno.
        @if ($resumen['fecha_limite']) Hasta el {{ \Illuminate\Support\Carbon::parse($resumen['fecha_limite'])->format('d/m/Y') }}. @endif
        <div class="small mt-1">Primero pides la matrícula y luego pagas. El alumno queda matriculado en cuanto se registra el pago.</div>
    </div>
@else
    <div class="alert alert-secondary">La matrícula en línea no está abierta en este momento. El colegio avisará cuando se abra.</div>
@endif

@forelse ($resumen['hijos'] as $hijo)
    <div class="card mb-3">
        <div class="card-body">
            <div class="fw-bold fs-5">{{ $hijo['nombre'] }}</div>
            <div class="text-muted small mb-2">Aula actual: {{ $hijo['aula_actual'] ?? 'sin aula' }}</div>

            @if ($hijo['estado'] === 'matriculado')
                <div class="text-success fw-bold"><i class="bi bi-patch-check-fill"></i> Matriculado en {{ $hijo['aula'] }} ({{ $resumen['anio'] }})</div>

            @elseif ($hijo['estado'] === 'pendiente_pago')
                <div class="fw-bold text-warning-emphasis"><i class="bi bi-hourglass-split"></i> Solicitud registrada para {{ $hijo['grado_solicitado'] }}</div>
                <p class="mb-2">Falta el pago de <strong>S/ {{ number_format($hijo['monto'], 2) }}</strong> para confirmar la matrícula.</p>
                @if ($resumen['pago_en_linea'])
                    <form action="{{ route('familia.matricula.pagar', $hijo['solicitud_id']) }}" method="POST" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-credit-card"></i> Pagar en línea ahora</button>
                    </form>
                    <div class="text-muted small">También puedes pagar en secretaría: al registrarse el pago, la matrícula se confirma sola.</div>
                @else
                    <div class="text-muted small">Paga en secretaría del colegio: al registrarse el pago, la matrícula se confirma sola y te llega un mensaje.</div>
                @endif

            @elseif ($resumen['abierta'])
                <form action="{{ route('familia.matricula.solicitar', $hijo['id']) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-sm">
                        <label class="form-label fw-bold mb-1" for="grado-{{ $hijo['id'] }}">Grado para {{ $resumen['anio'] }}</label>
                        <select name="grado_id" id="grado-{{ $hijo['id'] }}" class="form-select" required>
                            <option value="">Elige el grado…</option>
                            @foreach ($resumen['grados'] as $grado)
                                <option value="{{ $grado['id'] }}" @selected($hijo['grado_sugerido_id'] === $grado['id'])>{{ $grado['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-auto">
                        <button type="submit" class="btn btn-primary fw-bold w-100">Solicitar matrícula</button>
                    </div>
                </form>

            @else
                <div class="text-muted">Todavía sin matrícula para {{ $resumen['anio'] }}.</div>
            @endif
        </div>
    </div>
@empty
    <div class="card"><p class="text-center text-muted p-4 mb-0">Tu cuenta todavía no tiene alumnos vinculados. Avisa en secretaría.</p></div>
@endforelse
@endsection
