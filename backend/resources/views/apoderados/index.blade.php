@extends('layouts.panel')

@section('titulo', 'Apoderados')
@section('icono', 'bi-people-fill')
@section('subtitulo', 'Cuentas de padres, madres y apoderados para la app')

@section('contenido')
<p class="text-muted">
    Cuentas de padres, madres y apoderados para la app. Entran con su <strong>teléfono</strong> y la contraseña inicial
    <strong>{{ config('colegio.clave_inicial') }}</strong>, que la app les obliga a cambiar. Reciben un mensaje cada vez que su hijo
    ingresa, sale o tiene un permiso.
</p>

@if ($sinApoderado > 0)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Hay <strong>{{ $sinApoderado }}</strong> alumno(s) activo(s) sin ningún apoderado: nadie recibirá sus mensajes en la app.
    </div>
@endif

<datalist id="lista-alumnos">
    @foreach ($alumnos as $alumno)
        <option data-id="{{ $alumno->id }}" value="{{ $alumno->codigo }} — {{ $alumno->apellidos }}, {{ $alumno->nombres }}"></option>
    @endforeach
</datalist>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white fw-bold"><i class="bi bi-plus-lg"></i> Nueva cuenta</div>
            <div class="card-body">
                <form action="{{ route('apoderados.store') }}" method="POST" class="js-con-alumno">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="nombres">Nombre completo *</label>
                        <input type="text" name="nombres" id="nombres" class="form-control" maxlength="120" required value="{{ old('nombres') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="telefono">Teléfono (su usuario en la app) *</label>
                        <input type="tel" name="telefono" id="telefono" class="form-control" maxlength="20" required value="{{ old('telefono') }}" placeholder="987654321">
                    </div>
                    <div class="row">
                        <div class="col-7 mb-3">
                            <label class="form-label fw-bold" for="hijo_texto">Hijo/a</label>
                            <input type="text" id="hijo_texto" class="form-control js-alumno-texto" list="lista-alumnos" placeholder="Código o apellido">
                            <input type="hidden" name="hijos[0][alumno_id]" class="js-alumno-id" disabled>
                        </div>
                        <div class="col-5 mb-3">
                            <label class="form-label fw-bold" for="parentesco">Parentesco</label>
                            <select name="hijos[0][parentesco]" id="parentesco" class="form-select js-alumno-parentesco" disabled>
                                @foreach ($parentescos as $parentesco)
                                    <option value="{{ $parentesco->value }}" @selected($parentesco->value === 'apoderado')>{{ ucfirst($parentesco->value) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Crear cuenta</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <form method="GET" class="row g-2">
                    <div class="col-9"><input type="search" name="q" class="form-control" placeholder="Buscar por nombre o teléfono" value="{{ $filtros['q'] ?? '' }}" aria-label="Buscar apoderados"></div>
                    <div class="col-3"><button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Apoderado</th><th>Hijos</th><th class="text-center">App</th><th class="text-end pe-3">Acciones</th></tr></thead>
                    <tbody>
                    @forelse ($apoderados as $apoderado)
                        @php($activo = (bool) $apoderado->user?->activo)
                        <tr class="{{ $activo ? '' : 'inactivo' }}">
                            <td class="ps-3">
                                <div class="fw-bold">{{ $apoderado->nombres }}</div>
                                <small class="text-muted"><i class="bi bi-phone"></i> {{ $apoderado->telefono }}</small>
                                @if ($apoderado->user?->debe_cambiar_clave)
                                    <span class="badge bg-secondary" title="Aún no cambió la contraseña inicial">clave inicial</span>
                                @endif
                            </td>
                            <td>
                                @foreach ($apoderado->hijos as $hijo)
                                    <form action="{{ route('apoderados.hijos.destroy', [$apoderado, $hijo]) }}" method="POST"
                                          class="d-inline-flex align-items-center border rounded-pill bg-white px-2 me-1 mb-1 small"
                                          onsubmit="return confirm('¿Quitar a este alumno de la cuenta?')">
                                        @csrf @method('DELETE')
                                        <span title="{{ ucfirst($hijo->pivot->parentesco) }}">{{ $hijo->nombreCompleto() }}</span>
                                        <button type="submit" class="btn btn-sm p-0 ps-1 border-0 text-danger" title="Desvincular"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                @endforeach
                                <form action="{{ route('apoderados.hijos.store', $apoderado) }}" method="POST" class="d-flex gap-1 mt-1 js-con-alumno" style="max-width: 320px">
                                    @csrf
                                    <input type="text" class="form-control form-control-sm js-alumno-texto" list="lista-alumnos" placeholder="Agregar hijo/a…" aria-label="Alumno a vincular" required>
                                    <input type="hidden" name="alumno_id" class="js-alumno-id">
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Vincular"><i class="bi bi-plus-lg"></i></button>
                                </form>
                            </td>
                            <td class="text-center">
                                @if ($apoderado->dispositivos_count > 0)
                                    <span class="badge bg-success" title="Tiene la app y recibe notificaciones"><i class="bi bi-bell-fill"></i> {{ $apoderado->dispositivos_count }}</span>
                                @else
                                    <span class="badge bg-light text-muted border">sin app</span>
                                @endif
                            </td>
                            <td class="text-end pe-3" style="white-space: nowrap">
                                <form action="{{ route('apoderados.restablecer', $apoderado) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('La contraseña volverá a la inicial y se cerrarán sus sesiones en la app. ¿Continuar?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Restablecer contraseña"><i class="bi bi-key-fill"></i></button>
                                </form>
                                <form action="{{ route('apoderados.alternar', $apoderado) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('{{ $activo ? '¿Desactivar la cuenta? Dejará de entrar a la app.' : '¿Reactivar la cuenta?' }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $activo ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                            title="{{ $activo ? 'Desactivar' : 'Reactivar' }}"><i class="bi bi-power"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted p-4">Todavía no hay cuentas de apoderado.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($apoderados->hasPages())
                <div class="card-footer bg-white">{{ $apoderados->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// El campo de texto muestra "CÓDIGO — Apellidos, Nombres"; al servidor va el id.
const idPorTexto = {};
document.querySelectorAll('#lista-alumnos option').forEach(function (o) { idPorTexto[o.value] = o.dataset.id; });

document.querySelectorAll('form.js-con-alumno').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
        const texto = form.querySelector('.js-alumno-texto');
        const campoId = form.querySelector('.js-alumno-id');
        const parentesco = form.querySelector('.js-alumno-parentesco');
        const id = idPorTexto[texto.value.trim()];

        if (texto.value.trim() === '') { return; }        // hijo opcional al crear la cuenta
        if (!id) {
            ev.preventDefault();
            alert('Elige al alumno de la lista de sugerencias.');
            return;
        }
        campoId.disabled = false;
        campoId.value = id;
        if (parentesco) { parentesco.disabled = false; }
    });
});
</script>
@endpush
