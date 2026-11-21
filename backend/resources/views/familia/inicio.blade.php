@extends('layouts.familia')

@section('titulo', 'Mi familia')

@section('contenido')
<h1 class="h4 fw-bold mb-3">Hola, {{ \Illuminate\Support\Str::before($apoderado->nombres, ' ') }}</h1>

<h2 class="h6 text-uppercase text-muted fw-bold">Mis hijos</h2>
@forelse ($hijos as $hijo)
    <a href="{{ route('familia.hijo', $hijo) }}" class="card mb-2 text-decoration-none text-reset">
        <div class="card-body d-flex align-items-center gap-3">
            <div class="ph-icono" style="width: 46px; height: 46px; border-radius: 12px; background: rgba(var(--col-primario-rgb), .12); color: var(--col-primario); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold">{{ $hijo->nombreCompleto() }}</div>
                <div class="text-muted small">{{ $hijo->aula() ?? 'Sin aula' }}</div>
                @php($hoy = $hijo->asistenciaDeHoy)
                <div class="small mt-1">
                    @if ($hoy === null)
                        <span class="text-muted"><i class="bi bi-hourglass"></i> Hoy todavía no registra ingreso</span>
                    @elseif ($hoy->hora_salida === null)
                        <span class="text-success fw-bold"><i class="bi bi-box-arrow-in-right"></i> Ingresó a las {{ \App\Models\Asistencia::horaCorta($hoy->hora_entrada) }}</span>
                    @else
                        <span class="fw-bold"><i class="bi bi-box-arrow-right"></i> Ingresó {{ \App\Models\Asistencia::horaCorta($hoy->hora_entrada) }} · Salió {{ \App\Models\Asistencia::horaCorta($hoy->hora_salida) }}</span>
                    @endif
                    @if ($hoy?->estado === \App\Enums\EstadoAsistencia::Tarde)<span class="badge bg-warning text-dark">Tarde</span>@endif
                </div>
            </div>
            <i class="bi bi-chevron-right text-muted"></i>
        </div>
    </a>
@empty
    <div class="card"><p class="text-center text-muted p-4 mb-0">Tu cuenta todavía no tiene alumnos vinculados. Avisa en secretaría.</p></div>
@endforelse

<div class="d-flex justify-content-between align-items-center mt-4 mb-2 flex-wrap gap-2">
    <h2 class="h6 text-uppercase text-muted fw-bold mb-0">Mensajes @if ($noLeidos > 0)<span class="badge bg-danger">{{ $noLeidos }} sin leer</span>@endif</h2>
    @if ($noLeidos > 0)
        <form action="{{ route('familia.mensajes.leer') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary">Marcar todos como leídos</button>
        </form>
    @endif
</div>

@forelse ($mensajes as $mensaje)
    <div class="card mb-2" @if ($mensaje->leido_en === null) style="background: rgba(var(--col-primario-rgb), .05)" @endif>
        <div class="card-body py-2">
            <div class="d-flex justify-content-between gap-2">
                <span class="{{ $mensaje->leido_en === null ? 'fw-bold' : 'fw-semibold' }}">{{ $mensaje->titulo }}</span>
                <small class="text-muted text-nowrap">{{ $mensaje->created_at->format('d/m H:i') }}</small>
            </div>
            <div>{{ $mensaje->cuerpo }}</div>
        </div>
    </div>
@empty
    <div class="card"><p class="text-center text-muted p-4 mb-0">Aquí verás cuando tu hijo ingrese o salga del colegio, sus permisos, tareas y comunicados.</p></div>
@endforelse

{{ $mensajes->links('pagination::bootstrap-5') }}
@endsection
