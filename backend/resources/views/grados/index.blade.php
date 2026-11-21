@extends('layouts.panel')

@section('titulo', 'Grados y secciones')
@section('icono', 'bi-diagram-3-fill')
@section('subtitulo', 'Qué grados tiene el colegio en cada nivel y cuántas secciones (A, B, C…) tiene cada uno')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white fw-bold"><i class="bi bi-plus-lg"></i> Nuevo grado</div>
            <div class="card-body">
                <form action="{{ route('grados.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="nivel">Nivel *</label>
                        <select name="nivel" id="nivel" class="form-select" required>
                            @foreach ($niveles as $nivel)
                                <option value="{{ $nivel->value }}" @selected(old('nivel') === $nivel->value)>{{ $nivel->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="nombre">Nombre del grado *</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="50" required
                               value="{{ old('nombre') }}" placeholder="Ej: 3 años, 1ro Primaria">
                    </div>
                    <div class="row">
                        <div class="col-5 mb-3">
                            <label class="form-label fw-bold" for="orden">Orden</label>
                            <input type="number" name="orden" id="orden" class="form-control" min="0" max="99" value="{{ old('orden', 1) }}">
                        </div>
                        <div class="col-7 mb-3">
                            <label class="form-label fw-bold" for="secciones_texto">Secciones *</label>
                            <input type="text" id="secciones_texto" class="form-control" value="A" required placeholder="A, B, C">
                        </div>
                    </div>
                    <div class="form-text mb-3">
                        El orden es la posición dentro del nivel (1ro = 1, 2do = 2…). Para varias secciones sepáralas con comas: <code>A, B, C</code>.
                    </div>
                    <div id="secciones_campos"></div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Crear grado</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        @foreach ($niveles as $nivel)
            @php($gradosNivel = $porNivel->get($nivel->value, collect()))
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-bold d-flex justify-content-between">
                    <span>{{ $nivel->etiqueta() }}</span>
                    <span class="text-muted fw-normal small">{{ $gradosNivel->count() }} grado(s) · {{ $gradosNivel->sum(fn ($g) => $g->secciones->count()) }} sección(es)</span>
                </div>
                @if ($gradosNivel->isEmpty())
                    <p class="text-center text-muted p-4 mb-0">Sin grados en este nivel. Créalos con el formulario.</p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <tbody>
                            @foreach ($gradosNivel as $grado)
                                <tr>
                                    <td class="ps-3 fw-bold" style="width: 30%">{{ $grado->nombre }}</td>
                                    <td>
                                        @foreach ($grado->secciones as $seccion)
                                            <span class="d-inline-flex align-items-center border rounded-pill bg-white px-2 py-1 me-1 mb-1 {{ $seccion->activo ? '' : 'inactivo' }}">
                                                <strong class="me-1">{{ $seccion->nombre }}</strong>
                                                <small class="text-muted me-1">{{ $seccion->alumnos_count }} al.</small>
                                                <form action="{{ route('secciones.alternar', $seccion) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm p-0 px-1 border-0 {{ $seccion->activo ? 'text-success' : 'text-secondary' }}"
                                                            title="{{ $seccion->activo ? 'Desactivar sección' : 'Reactivar sección' }}"><i class="bi bi-power"></i></button>
                                                </form>
                                                @if ($seccion->alumnos_count === 0)
                                                    <form action="{{ route('secciones.destroy', $seccion) }}" method="POST" class="d-inline"
                                                          onsubmit="return confirm('¿Eliminar la sección {{ $seccion->nombre }}? No tiene alumnos.')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-sm p-0 px-1 border-0 text-danger" title="Eliminar sección"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                @endif
                                            </span>
                                        @endforeach
                                    </td>
                                    <td class="text-end pe-3" style="white-space: nowrap; width: 1%">
                                        <form action="{{ route('grados.secciones.store', $grado) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Agrega la siguiente letra libre"><i class="bi bi-plus-lg"></i> Sección</button>
                                        </form>
                                        @if ($grado->secciones->sum('alumnos_count') === 0)
                                            <form action="{{ route('grados.destroy', $grado) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar el grado {{ $grado->nombre }} con sus secciones?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar grado"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
// "A, B, C" → un campo secciones[] por cada sección, que es lo que espera el servidor.
document.querySelector('form[action$="/grados"]').addEventListener('submit', function () {
    const campos = document.getElementById('secciones_campos');
    campos.innerHTML = '';
    document.getElementById('secciones_texto').value.split(/[\s,;]+/).filter(Boolean).forEach(function (nombre) {
        const campo = document.createElement('input');
        campo.type = 'hidden'; campo.name = 'secciones[]'; campo.value = nombre;
        campos.appendChild(campo);
    });
});
</script>
@endpush
