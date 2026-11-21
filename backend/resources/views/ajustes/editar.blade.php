@extends('layouts.panel')

@section('titulo', 'Configuración')
@section('icono', 'bi-gear-fill')
@section('subtitulo', 'Identidad del colegio y reglas de asistencia')

@section('contenido')
<form action="{{ route('ajustes.actualizar') }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-palette-fill text-secondary"></i> Identidad</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="nombre_colegio">Nombre del colegio *</label>
                        <input type="text" name="nombre_colegio" id="nombre_colegio" class="form-control" maxlength="120" required value="{{ old('nombre_colegio', $marca->nombre) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="color_primario">Color institucional *</label>
                        <input type="color" name="color_primario" id="color_primario" class="form-control form-control-color" value="{{ old('color_primario', $marca->color) }}">
                        <div class="form-text">Tiñe el menú, los botones y la pantalla de inicio de sesión.</div>
                    </div>
                    <div>
                        <label class="form-label fw-bold" for="logo">Logo</label>
                        @if ($marca->logo)
                            <div class="mb-2"><img src="{{ asset($marca->logo) }}" alt="Logo actual" style="max-height: 70px; max-width: 200px; object-fit: contain"></div>
                        @endif
                        <input type="file" name="logo" id="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text">PNG, JPG o WEBP, hasta 1 MB. Déjalo vacío para conservar el actual.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-clock-fill text-secondary"></i> Asistencia</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="hora_entrada">Hora de entrada *</label>
                        <input type="time" name="hora_entrada" id="hora_entrada" class="form-control" required value="{{ old('hora_entrada', $horaEntrada) }}" style="max-width: 160px">
                        <div class="form-text">Hasta esa hora inclusive el ingreso es puntual.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="tolerancia_minutos">Tolerancia (minutos) *</label>
                        <input type="number" name="tolerancia_minutos" id="tolerancia_minutos" class="form-control" min="0" max="120" required value="{{ old('tolerancia_minutos', $tolerancia) }}" style="max-width: 160px">
                        <div class="form-text">Minutos de gracia después de la hora de entrada antes de marcar tardanza.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="periodos_por_anio">Periodos de evaluación *</label>
                        <select name="periodos_por_anio" id="periodos_por_anio" class="form-select" style="max-width: 260px">
                            @foreach ($opcionesPeriodos as $cantidad => $etiqueta)
                                <option value="{{ $cantidad }}" @selected((int) old('periodos_por_anio', $periodosPorAnio) === $cantidad)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Cómo se divide el año para registrar las notas.</div>
                    </div>
                    <div>
                        <label class="form-label fw-bold" for="minutos_minimos_para_salida">Tiempo mínimo para registrar la salida (minutos) *</label>
                        <input type="number" name="minutos_minimos_para_salida" id="minutos_minimos_para_salida" class="form-control" min="0" max="600" required value="{{ old('minutos_minimos_para_salida', $minutosSalida) }}" style="max-width: 160px">
                        <div class="form-text">Un segundo escaneo antes de ese tiempo no cuenta como salida: evita avisos falsos por un doble escaneo en la puerta.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><i class="bi bi-journal-check text-secondary"></i> Calificación</div>
        <div class="card-body">
            <p class="text-muted small">Con qué se califica en cada nivel al registrar las notas.</p>
            <div class="row g-3">
                @foreach ($niveles as $nivel)
                    <div class="col-md-4">
                        <label class="form-label fw-bold" for="escala_{{ $nivel->value }}">{{ $nivel->etiqueta() }} *</label>
                        <select name="escalas[{{ $nivel->value }}]" id="escala_{{ $nivel->value }}" class="form-select">
                            @foreach ($opcionesEscala as $opcion)
                                <option value="{{ $opcion->value }}" @selected(old('escalas.'.$nivel->value, $escalaDe($nivel)->value) === $opcion->value)>{{ $opcion->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
            <div class="form-text mt-2">
                Literal: AD (logro destacado), A (logro esperado), B (en proceso), C (en inicio). Las letras no se promedian.
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg fw-bold mt-3"><i class="bi bi-check-lg"></i> Guardar configuración</button>
</form>
@endsection
