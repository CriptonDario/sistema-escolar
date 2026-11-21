@extends('layouts.panel')

@section('titulo', 'Permisos')
@section('icono', 'bi-card-checklist')
@section('subtitulo', 'Al registrar o anular un permiso, el apoderado recibe el aviso en la app')

@section('contenido')
<datalist id="lista-alumnos">
    @foreach ($alumnos as $alumno)
        <option data-id="{{ $alumno->id }}" value="{{ $alumno->codigo }} — {{ $alumno->apellidos }}, {{ $alumno->nombres }}"></option>
    @endforeach
</datalist>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Nuevo permiso</div>
            <div class="card-body">
                <form action="{{ route('permisos.store') }}" method="POST" id="form-permiso">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="alumno_texto">Alumno *</label>
                        <input type="text" id="alumno_texto" class="form-control" list="lista-alumnos" required placeholder="Código o apellido">
                        <input type="hidden" name="alumno_id" id="alumno_id" value="{{ old('alumno_id') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="tipo">Tipo *</label>
                        <select name="tipo" id="tipo" class="form-select" required>
                            @foreach ($tipos as $tipo)
                                <option value="{{ $tipo->value }}" data-hora="{{ $tipo->requiereHora() ? 1 : 0 }}" @selected(old('tipo') === $tipo->value)>{{ $tipo->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-7 mb-3">
                            <label class="form-label fw-bold" for="fecha">Fecha *</label>
                            <input type="date" name="fecha" id="fecha" class="form-control" required value="{{ old('fecha', today()->toDateString()) }}">
                        </div>
                        <div class="col-5 mb-3" id="grupo-hora">
                            <label class="form-label fw-bold" for="hora">Hora *</label>
                            <input type="time" name="hora" id="hora" class="form-control" value="{{ old('hora') }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="motivo">Motivo *</label>
                        <input type="text" name="motivo" id="motivo" class="form-control" maxlength="255" required value="{{ old('motivo') }}" placeholder="Ej: cita médica">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Registrar y avisar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label small mb-0" for="desde">Desde</label>
                        <input type="date" name="desde" id="desde" class="form-control form-control-sm" value="{{ $desde }}">
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-0" for="hasta">Hasta</label>
                        <input type="date" name="hasta" id="hasta" class="form-control form-control-sm" value="{{ $hasta }}">
                    </div>
                    <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i> Filtrar</button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="ps-3">Fecha</th><th>Alumno</th><th>Permiso</th><th>Motivo</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($permisos as $permiso)
                        <tr class="{{ $permiso->estaVigente() ? '' : 'inactivo' }}">
                            <td class="ps-3" style="white-space: nowrap">
                                {{ $permiso->fecha->format('d/m/Y') }}
                                @if ($permiso->hora)<br><small class="text-muted">{{ substr($permiso->hora, 0, 5) }}</small>@endif
                            </td>
                            <td>
                                <div class="fw-bold">{{ $permiso->alumno->nombreCompleto() }}</div>
                                <small class="text-muted">{{ $permiso->alumno->codigo }} · {{ $permiso->alumno->aula() ?? 'Sin aula' }}</small>
                                @if ($permiso->alumno->apoderados->isEmpty())
                                    <br><small class="text-danger"><i class="bi bi-bell-slash"></i> sin apoderado en la app</small>
                                @endif
                            </td>
                            <td>
                                {{ $permiso->tipo->etiqueta() }}
                                @unless ($permiso->estaVigente())<span class="badge bg-secondary">Anulado</span>@endunless
                            </td>
                            <td>{{ $permiso->motivo }}</td>
                            <td class="text-end pe-3">
                                @if ($permiso->estaVigente())
                                    <form action="{{ route('permisos.destroy', $permiso) }}" method="POST" onsubmit="return confirm('¿Anular este permiso? Se avisará al apoderado.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular permiso"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted p-4">Sin permisos en este rango de fechas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($permisos->hasPages())
                <div class="card-footer bg-white">{{ $permisos->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const idPorTexto = {};
document.querySelectorAll('#lista-alumnos option').forEach(function (o) { idPorTexto[o.value] = o.dataset.id; });

// La inasistencia justificada no lleva hora.
function ajustarHora() {
    const llevaHora = document.getElementById('tipo').selectedOptions[0].dataset.hora === '1';
    document.getElementById('grupo-hora').style.display = llevaHora ? '' : 'none';
    document.getElementById('hora').required = llevaHora;
    if (!llevaHora) { document.getElementById('hora').value = ''; }
}
document.getElementById('tipo').addEventListener('change', ajustarHora);
ajustarHora();

document.getElementById('form-permiso').addEventListener('submit', function (ev) {
    const id = idPorTexto[document.getElementById('alumno_texto').value.trim()];
    if (!id) { ev.preventDefault(); alert('Elige al alumno de la lista de sugerencias.'); return; }
    document.getElementById('alumno_id').value = id;
});
</script>
@endpush
