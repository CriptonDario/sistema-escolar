@extends('layouts.panel')

@section('titulo', 'Conducta y tutoría')
@section('icono', 'bi-award-fill')
@section('subtitulo', 'Méritos, deméritos, observaciones y citaciones; el apoderado recibe el aviso en la app')

@section('contenido')
<datalist id="lista-alumnos">
    @foreach ($alumnos as $alumno)
        <option data-id="{{ $alumno->id }}" value="{{ $alumno->codigo }} — {{ $alumno->apellidos }}, {{ $alumno->nombres }}"></option>
    @endforeach
</datalist>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-plus-lg"></i> Nuevo registro</div>
            <div class="card-body">
                <form action="{{ route('conducta.store') }}" method="POST" id="form-conducta">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="alumno_texto">Alumno *</label>
                        <input type="text" id="alumno_texto" class="form-control" list="lista-alumnos" required placeholder="Código o apellido">
                        <input type="hidden" name="alumno_id" id="alumno_id">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="tipo">Tipo *</label>
                            <select name="tipo" id="tipo" class="form-select" required>
                                @foreach ($tipos as $tipo)
                                    <option value="{{ $tipo->value }}" @selected(old('tipo') === $tipo->value)>{{ $tipo->etiqueta() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold" for="fecha">Fecha *</label>
                            <input type="date" name="fecha" id="fecha" class="form-control" required value="{{ old('fecha', today()->toDateString()) }}" max="{{ today()->toDateString() }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="descripcion">Descripción *</label>
                        <textarea name="descripcion" id="descripcion" class="form-control" rows="3" maxlength="500" required>{{ old('descripcion') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Registrar y avisar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <form method="GET" class="row g-2">
                    <div class="col-9"><input type="search" name="q" class="form-control form-control-sm" placeholder="Buscar alumno" value="{{ $filtros['q'] ?? '' }}" aria-label="Buscar alumno"></div>
                    <div class="col-3"><button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="ps-3">Fecha</th><th>Alumno</th><th>Registro</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($registros as $registro)
                        <tr class="{{ $registro->anulado_en ? 'inactivo' : '' }}">
                            <td class="ps-3" style="white-space: nowrap">{{ $registro->fecha->format('d/m/Y') }}</td>
                            <td>
                                <div class="fw-bold">{{ $registro->alumno->nombreCompleto() }}</div>
                                <small class="text-muted">{{ $registro->alumno->aula() ?? 'Sin aula' }}</small>
                            </td>
                            <td>
                                <span class="badge {{ $registro->tipo === \App\Enums\TipoConducta::Merito ? 'bg-success' : ($registro->tipo === \App\Enums\TipoConducta::Demerito ? 'bg-danger' : 'bg-secondary') }}">{{ $registro->tipo->etiqueta() }}</span>
                                @if ($registro->anulado_en)<span class="badge bg-secondary">Anulado</span>@endif
                                <br>{{ $registro->descripcion }}
                            </td>
                            <td class="text-end pe-3">
                                @unless ($registro->anulado_en)
                                    <form action="{{ route('conducta.destroy', $registro) }}" method="POST" onsubmit="return confirm('¿Anular este registro?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted p-4">Sin registros de conducta.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($registros->hasPages())
                <div class="card-footer bg-white">{{ $registros->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const idPorTexto = {};
document.querySelectorAll('#lista-alumnos option').forEach(function (o) { idPorTexto[o.value] = o.dataset.id; });
document.getElementById('form-conducta').addEventListener('submit', function (ev) {
    const id = idPorTexto[document.getElementById('alumno_texto').value.trim()];
    if (!id) { ev.preventDefault(); alert('Elige al alumno de la lista de sugerencias.'); return; }
    document.getElementById('alumno_id').value = id;
});
</script>
@endpush
