@extends('layouts.panel')

@section('titulo', 'Comunicados')
@section('icono', 'bi-megaphone-fill')
@section('subtitulo', 'Llegan como mensaje a la app de los apoderados')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-send-fill"></i> Nuevo comunicado</div>
            <div class="card-body">
                <form action="{{ route('comunicados.store') }}" method="POST" onsubmit="return confirm('¿Publicar y enviar a los apoderados?')">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="titulo">Título *</label>
                        <input type="text" name="titulo" id="titulo" class="form-control" maxlength="120" required value="{{ old('titulo') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="cuerpo">Mensaje *</label>
                        <textarea name="cuerpo" id="cuerpo" class="form-control" rows="5" maxlength="5000" required>{{ old('cuerpo') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="alcance">Para *</label>
                        <select name="alcance" id="alcance" class="form-select" required>
                            <option value="todos" @selected(old('alcance', 'todos') === 'todos')>Todo el colegio</option>
                            <option value="nivel" @selected(old('alcance') === 'nivel')>Un nivel</option>
                            <option value="seccion" @selected(old('alcance') === 'seccion')>Un aula</option>
                        </select>
                    </div>
                    <div class="mb-3" id="grupo-nivel">
                        <label class="form-label fw-bold" for="nivel">Nivel</label>
                        <select name="nivel" id="nivel" class="form-select">
                            @foreach ($niveles as $nivel)
                                <option value="{{ $nivel->value }}" @selected(old('nivel') === $nivel->value)>{{ $nivel->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3" id="grupo-seccion">
                        <label class="form-label fw-bold" for="seccion_id">Aula</label>
                        <select name="seccion_id" id="seccion_id" class="form-select">
                            @foreach ($aulas as $aula)
                                <option value="{{ $aula->id }}" @selected(old('seccion_id') == $aula->id)>{{ $aula->nombreCompleto() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Publicar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        @forelse ($comunicados as $comunicado)
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <h5 class="fw-bold mb-1">{{ $comunicado->titulo }}</h5>
                        <small class="text-muted">{{ $comunicado->created_at->format('d/m/Y H:i') }}</small>
                    </div>
                    <p class="mb-2" style="white-space: pre-line">{{ $comunicado->cuerpo }}</p>
                    <span class="badge bg-light text-dark border">{{ $comunicado->destino() }}</span>
                    <span class="badge bg-light text-dark border"><i class="bi bi-people"></i> {{ $comunicado->destinatarios }} apoderado(s)</span>
                </div>
            </div>
        @empty
            <div class="card"><p class="text-center text-muted p-4 mb-0">Todavía no se publicó ningún comunicado.</p></div>
        @endforelse
        {{ $comunicados->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

@push('scripts')
<script>
function ajustarAlcance() {
    const alcance = document.getElementById('alcance').value;
    document.getElementById('grupo-nivel').style.display = alcance === 'nivel' ? '' : 'none';
    document.getElementById('grupo-seccion').style.display = alcance === 'seccion' ? '' : 'none';
    document.getElementById('nivel').disabled = alcance !== 'nivel';
    document.getElementById('seccion_id').disabled = alcance !== 'seccion';
}
document.getElementById('alcance').addEventListener('change', ajustarAlcance);
ajustarAlcance();
</script>
@endpush
