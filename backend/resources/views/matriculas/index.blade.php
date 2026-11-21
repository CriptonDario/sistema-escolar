@extends('layouts.panel')

@section('titulo', 'Matrículas '.$anio)
@section('icono', 'bi-journal-bookmark-fill')

@section('contenido')
{{-- Matrícula en línea: el interruptor que abre el proceso a los apoderados --}}
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-globe2"></i> Matrícula en línea</span>
        @if ($enLinea->abierta())
            <span class="badge bg-success">Abierta para {{ $enLinea->anio() }}</span>
        @elseif ($enLinea->activada())
            <span class="badge bg-warning text-dark">Activada, pero no disponible</span>
        @else
            <span class="badge bg-secondary">Cerrada</span>
        @endif
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Al abrirla, los apoderados pueden pedir la matrícula de sus hijos desde la app o el portal.
            El alumno queda matriculado solo cuando se registra el pago: en línea, o en secretaría con un pago de tipo Matrícula.
        </p>

        @error('matricula_abierta')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
        @error('matricula_anio')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

        <form action="{{ route('matriculas.en-linea') }}" method="POST" class="row g-3 align-items-end">
            @csrf @method('PUT')
            <div class="col-sm-auto">
                <div class="form-check form-switch fs-5">
                    <input class="form-check-input" type="checkbox" role="switch" id="matricula_abierta" name="matricula_abierta" value="1" @checked(old('matricula_abierta', $enLinea->activada()))>
                    <label class="form-check-label" for="matricula_abierta">Abierta</label>
                </div>
            </div>
            <div class="col-sm-2">
                <label class="form-label fw-bold mb-1" for="matricula_anio">Año</label>
                <input type="number" name="matricula_anio" id="matricula_anio" class="form-control" min="{{ now()->year }}" max="2100" required value="{{ old('matricula_anio', $enLinea->anio()) }}">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-bold mb-1" for="matricula_hasta">Hasta (opcional)</label>
                <input type="date" name="matricula_hasta" id="matricula_hasta" class="form-control" value="{{ old('matricula_hasta', $enLinea->fechaLimite()?->toDateString()) }}">
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-primary fw-bold">Guardar</button>
            </div>
        </form>

        <ul class="small mt-3 mb-0 ps-3">
            @if ($enLinea->concepto())
                <li>Costo: <strong>S/ {{ number_format($enLinea->monto(), 2) }}</strong> (concepto "{{ $enLinea->concepto()->nombre }}").</li>
            @else
                <li class="text-danger">Falta crear en <a href="{{ route('conceptos.index') }}">Conceptos de pago</a> un concepto de tipo Matrícula con su monto.</li>
            @endif
            @if ($enLinea->pagoEnLineaDisponible())
                <li>Pago en línea: <strong class="text-success">activo</strong> (Mercado Pago).</li>
            @else
                <li>Pago en línea: sin configurar; por ahora solo se paga en secretaría. Se activa en <a href="{{ route('integraciones.editar') }}">Integraciones</a>.</li>
            @endif
        </ul>

        @if ($solicitudes->isNotEmpty())
            <h3 class="h6 fw-bold mt-4">Solicitudes esperando pago ({{ $solicitudes->count() }})</h3>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Alumno</th><th>Grado pedido</th><th>Año</th><th>Apoderado</th><th>Pedido el</th><th class="text-end">Monto</th></tr></thead>
                    <tbody>
                        @foreach ($solicitudes as $solicitud)
                            <tr>
                                <td>{{ $solicitud->alumno->apellidos }}, {{ $solicitud->alumno->nombres }}</td>
                                <td>{{ $solicitud->grado->nombre }}</td>
                                <td>{{ $solicitud->anio }}</td>
                                <td>{{ $solicitud->apoderado?->nombres ?? '—' }} <small class="text-muted">{{ $solicitud->apoderado?->telefono }}</small></td>
                                <td>{{ $solicitud->created_at->format('d/m/Y') }}</td>
                                <td class="text-end">S/ {{ number_format($solicitud->monto, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-text">Para confirmar una, registra su pago de Matrícula en <a href="{{ route('pagos.index') }}">Pagos</a> con el mismo año.</div>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Matricular</div>
            <div class="card-body">
                @if ($pendientes->isEmpty())
                    <p class="text-muted mb-0">Todos los alumnos activos ya están matriculados en {{ $anio }}.</p>
                @else
                    <form action="{{ route('matriculas.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="anio" value="{{ $anio }}">
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="alumno_id">Alumno * <small class="text-muted fw-normal">({{ $pendientes->count() }} sin matricular)</small></label>
                            <select name="alumno_id" id="alumno_id" class="form-select" required>
                                <option value="">Elige un alumno…</option>
                                @foreach ($pendientes as $alumno)
                                    <option value="{{ $alumno->id }}" data-seccion="{{ $alumno->seccion_id }}" @selected(old('alumno_id') == $alumno->id)>{{ $alumno->apellidos }}, {{ $alumno->nombres }} ({{ $alumno->codigo }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="seccion_id">Aula *</label>
                            <select name="seccion_id" id="seccion_id" class="form-select" required>
                                <option value="">Elige el aula…</option>
                                @foreach ($aulas as $aula)
                                    <option value="{{ $aula->id }}" @selected(old('seccion_id') == $aula->id)>{{ $aula->nombreCompleto() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="fecha">Fecha *</label>
                            <input type="date" name="fecha" id="fecha" class="form-control" required value="{{ old('fecha', today()->toDateString()) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="observacion">Observación</label>
                            <input type="text" name="observacion" id="observacion" class="form-control" maxlength="255" value="{{ old('observacion') }}">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Matricular</button>
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
                        <label class="form-label small mb-0" for="f_anio">Año</label>
                        <input type="number" name="anio" id="f_anio" class="form-control form-control-sm" value="{{ $anio }}" min="2000" max="2100" style="width: 100px">
                    </div>
                    <div class="col">
                        <label class="form-label small mb-0" for="f_seccion">Aula</label>
                        <select name="seccion_id" id="f_seccion" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach ($aulas as $aula)
                                <option value="{{ $aula->id }}" @selected(($filtros['seccion_id'] ?? null) == $aula->id)>{{ $aula->nombreCompleto() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i> Filtrar</button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="ps-3">Alumno</th><th>Aula</th><th>Fecha</th><th>Estado</th></tr></thead>
                    <tbody>
                    @forelse ($matriculas as $matricula)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold">{{ $matricula->alumno->apellidos }}, {{ $matricula->alumno->nombres }}</div>
                                <small class="text-muted">{{ $matricula->alumno->codigo }}@if ($matricula->observacion) · {{ $matricula->observacion }}@endif</small>
                            </td>
                            <td>{{ $matricula->seccion->nombreCompleto() }}</td>
                            <td>{{ $matricula->fecha->format('d/m/Y') }}</td>
                            <td>
                                <form action="{{ route('matriculas.estado', $matricula) }}" method="POST">
                                    @csrf @method('PUT')
                                    <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Estado de la matrícula" style="min-width: 130px">
                                        @foreach ($estados as $estado)
                                            <option value="{{ $estado->value }}" @selected($matricula->estado === $estado)>{{ ucfirst($estado->value) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted p-4">Sin matrículas en {{ $anio }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($matriculas->hasPages())
                <div class="card-footer bg-white">{{ $matriculas->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Al elegir al alumno se propone el aula que ya tiene.
const selAlumno = document.getElementById('alumno_id');
if (selAlumno) {
    selAlumno.addEventListener('change', function () {
        const aula = this.selectedOptions[0].dataset.seccion;
        if (aula) { document.getElementById('seccion_id').value = aula; }
    });
}
</script>
@endpush
