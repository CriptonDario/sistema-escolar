@extends('layouts.panel')

@section('titulo', 'Dashboard')

@section('cabecera')
<div class="hero-banda d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1>Bienvenido, {{ auth()->user()->nombre }} 👋</h1>
        <div class="hero-sub">{{ ucfirst(now()->translatedFormat('l j \d\e F \d\e Y')) }} — {{ $marca->nombre }}</div>
    </div>
    @if (auth()->user()->tieneRol(\App\Enums\Rol::Admin, \App\Enums\Rol::Guardia))
        <a href="{{ route('kiosco') }}" class="btn btn-light btn-lg fw-bold"><i class="bi bi-qr-code-scan"></i> Abrir Kiosco</a>
    @endif
</div>
@endsection

@section('contenido')
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card kpi-card">
            <div class="kpi-icono" style="background: var(--col-primario)"><i class="bi bi-mortarboard"></i></div>
            <div><h6>Alumnos presentes</h6><div class="kpi-valor">{{ $alumnos['presentes'] }}<small class="text-muted fs-6 fw-normal"> / {{ $alumnos['total'] }}</small></div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card kpi-card">
            <div class="kpi-icono" style="background: #16a34a"><i class="bi bi-person-check"></i></div>
            <div><h6>Personal presente</h6><div class="kpi-valor">{{ $personal['presentes'] }}<small class="text-muted fs-6 fw-normal"> / {{ $personal['total'] }}</small></div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card kpi-card">
            <div class="kpi-icono" style="background: #e8a013"><i class="bi bi-clock-history"></i></div>
            <div>
                <h6>Llegadas tarde</h6>
                <div class="kpi-valor">{{ $alumnos['tardanzas'] + $personal['tardanzas'] }}</div>
                <span class="badge rounded-pill text-danger" style="background: rgba(var(--col-primario-rgb), .10)">Después de {{ $horaEntrada }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-mortarboard-fill text-secondary"></i> Alumnos hoy</div>
            <div class="card-body">
                @php($sinRegistro = max(0, $alumnos['total'] - $alumnos['presentes']))
                @foreach ([
                    ['Puntuales', $alumnos['presentes'] - $alumnos['tardanzas'], 'var(--col-primario)'],
                    ['Tardanzas', $alumnos['tardanzas'], '#e8a013'],
                    ['Ya salieron', $alumnos['salieron'], '#0d6efd'],
                    ['Sin registro', $sinRegistro, '#cbd5e1'],
                ] as [$etiqueta, $valor, $color])
                    <div class="d-flex justify-content-between align-items-center py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                        <span><span class="d-inline-block rounded-1 me-2" style="width: 11px; height: 11px; background: {{ $color }}"></span>{{ $etiqueta }}</span>
                        <strong>{{ $valor }}</strong>
                    </div>
                @endforeach
                <hr>
                <div class="d-flex justify-content-between small text-muted"><span>Secciones activas</span><strong>{{ $secciones }}</strong></div>
                <div class="d-flex justify-content-between small text-muted"><span>Apoderados con cuenta</span><strong>{{ $apoderados }}</strong></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-list-check text-secondary"></i> Últimas marcas</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="ps-3">Persona</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead>
                    <tbody>
                    @forelse ($ultimas as $marcaDelDia)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold">{{ $marcaDelDia->persona->nombreCompleto() }}</div>
                                <small class="text-muted">
                                    {{ $marcaDelDia->persona instanceof \App\Models\Alumno ? ($marcaDelDia->persona->aula() ?? 'Sin aula') : ($marcaDelDia->persona->cargo ?? 'Personal') }}
                                </small>
                            </td>
                            <td>{{ \App\Models\Asistencia::horaCorta($marcaDelDia->hora_entrada) }}</td>
                            <td>{{ \App\Models\Asistencia::horaCorta($marcaDelDia->hora_salida) ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $marcaDelDia->estado === \App\Enums\EstadoAsistencia::Tarde ? 'bg-warning text-dark' : 'bg-success' }}">
                                    {{ ucfirst($marcaDelDia->estado->value) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted p-4">Todavía no hay marcas hoy.<br><small>Aparecen cuando el kiosco empieza a registrar.</small></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
