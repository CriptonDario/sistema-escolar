@extends('layouts.familia')

@section('titulo', $alumno->nombreCompleto())

@section('contenido')
<a href="{{ route('familia.inicio') }}" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Mis hijos</a>
<h1 class="h4 fw-bold mb-0 mt-1">{{ $alumno->nombreCompleto() }}</h1>
<p class="text-muted">{{ $alumno->aula() ?? 'Sin aula' }}</p>

{{-- Se desplaza hacia los lados en pantallas angostas en vez de partirse en varias filas. --}}
<ul class="nav nav-pills flex-nowrap overflow-auto mb-3 pb-1" style="white-space: nowrap">
    @foreach ($secciones as $clave => $nombre)
        <li class="nav-item">
            <a class="nav-link {{ $ver === $clave ? 'active' : '' }}" href="{{ route('familia.hijo', [$alumno, 'ver' => $clave]) }}"
               @if ($ver === $clave) aria-current="page" @endif>{{ $nombre }}</a>
        </li>
    @endforeach
</ul>

@switch($ver)
    @case('asistencia')
        @php($inicioMes = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $datos['mes']))
        <div class="d-flex justify-content-between align-items-center mb-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('familia.hijo', [$alumno, 'ver' => 'asistencia', 'mes' => $inicioMes->copy()->subMonth()->format('Y-m')]) }}" aria-label="Mes anterior"><i class="bi bi-chevron-left"></i></a>
            <strong>{{ ucfirst($inicioMes->translatedFormat('F Y')) }}</strong>
            @if ($inicioMes->isSameMonth(now()))
                <span style="width: 34px"></span>
            @else
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('familia.hijo', [$alumno, 'ver' => 'asistencia', 'mes' => $inicioMes->copy()->addMonth()->format('Y-m')]) }}" aria-label="Mes siguiente"><i class="bi bi-chevron-right"></i></a>
            @endif
        </div>
        @foreach ($datos['permisos'] as $permiso)
            <div class="card mb-2"><div class="card-body py-2">
                <span class="badge bg-info text-dark">Permiso</span> <strong>{{ $permiso->tipo->etiqueta() }}</strong>
                · {{ $permiso->fecha->format('d/m/Y') }}@if ($permiso->hora) {{ substr($permiso->hora, 0, 5) }}@endif
                <div class="text-muted small">{{ $permiso->motivo }}</div>
            </div></div>
        @endforeach
        @forelse ($datos['dias'] as $dia)
            <div class="card mb-2"><div class="card-body py-2 d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $dia->fecha->format('d/m/Y') }}</strong>
                    <div class="text-muted small">Entrada {{ \App\Models\Asistencia::horaCorta($dia->hora_entrada) ?? '—' }} · Salida {{ \App\Models\Asistencia::horaCorta($dia->hora_salida) ?? '—' }}</div>
                </div>
                <span class="badge {{ $dia->estado === \App\Enums\EstadoAsistencia::Tarde ? 'bg-warning text-dark' : 'bg-success' }}">{{ ucfirst($dia->estado->value) }}</span>
            </div></div>
        @empty
            @if ($datos['permisos']->isEmpty())
                <div class="card"><p class="text-center text-muted p-4 mb-0">Sin registros este mes.</p></div>
            @endif
        @endforelse
        @break

    @case('carne')
        @if (blank($alumno->codigo))
            <div class="card"><p class="text-center text-muted p-4 mb-0">Este alumno todavía no tiene código de carné. Avisa en secretaría.</p></div>
        @else
            @include('carnes.alumno', ['alumno' => $datos['alumno']])
            <p class="text-center text-muted mt-3">
                Este es el carné de tu hijo. Con este código se registra su ingreso y su salida en la puerta.
            </p>
            <div class="text-center">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir carné</button>
            </div>
        @endif
        @break

    @case('notas')
        @forelse ($datos['materias'] as $materia)
            <div class="card mb-2"><div class="card-body">
                <div class="d-flex justify-content-between">
                    <strong>{{ $materia['materia'] }}</strong>
                    @if ($materia['promedio'] !== null)<span class="badge bg-primary">Promedio {{ $materia['promedio'] }}</span>@endif
                </div>
                <div class="row text-center mt-2">
                    @foreach ($datos['periodos'] as $numero => $nombre)
                        <div class="col">
                            <div class="fs-4 fw-bold">{{ $materia['periodos'][$numero] ?? '—' }}</div>
                            <div class="text-muted" style="font-size: .7rem">{{ $nombre }}</div>
                        </div>
                    @endforeach
                </div>
            </div></div>
        @empty
            <div class="card"><p class="text-center text-muted p-4 mb-0">Todavía no hay notas registradas este año.</p></div>
        @endforelse
        @if ($datos['letras'])
            <p class="text-muted small mt-2">
                @foreach ($datos['letras'] as $letra => $significado){{ $letra }}: {{ $significado }}@if (! $loop->last) · @endif @endforeach
            </p>
        @endif
        @break

    @case('tareas')
        @forelse ($datos['tareas'] as $tarea)
            <div class="card mb-2 {{ $tarea['vencida'] ? 'inactivo' : '' }}"><div class="card-body py-2 d-flex justify-content-between gap-2">
                <div>
                    <span class="badge bg-light text-dark border">{{ ucfirst($tarea['tipo']) }}</span> <strong>{{ $tarea['titulo'] }}</strong>
                    @if ($tarea['materia'])<div class="text-muted small">{{ $tarea['materia'] }}</div>@endif
                    @if ($tarea['descripcion'])<div class="small">{{ $tarea['descripcion'] }}</div>@endif
                </div>
                <strong class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($tarea['fecha_entrega'])->format('d/m') }}</strong>
            </div></div>
        @empty
            <div class="card"><p class="text-center text-muted p-4 mb-0">No hay tareas ni exámenes próximos.</p></div>
        @endforelse
        @break

    @case('horario')
        @forelse ($datos['dias'] as $dia)
            <h2 class="h6 text-uppercase text-muted fw-bold mt-3">{{ $dia['nombre'] }}</h2>
            @foreach ($dia['bloques'] as $bloque)
                <div class="card mb-1"><div class="card-body py-2 d-flex gap-3">
                    <strong class="text-nowrap">{{ $bloque['inicio'] }}–{{ $bloque['fin'] }}</strong>
                    <div>{{ $bloque['nombre'] }}@if ($bloque['docente'])<div class="text-muted small">{{ $bloque['docente'] }}</div>@endif</div>
                </div></div>
            @endforeach
        @empty
            <div class="card"><p class="text-center text-muted p-4 mb-0">El horario todavía no está cargado.</p></div>
        @endforelse
        @break

    @case('conducta')
        @forelse ($datos['registros'] as $registro)
            <div class="card mb-2"><div class="card-body py-2">
                <span class="badge {{ ['merito' => 'bg-success', 'demerito' => 'bg-danger'][$registro['tipo']] ?? 'bg-secondary' }}">{{ $registro['tipo_etiqueta'] }}</span>
                <small class="text-muted">{{ \Illuminate\Support\Carbon::parse($registro['fecha'])->format('d/m/Y') }}</small>
                <div>{{ $registro['descripcion'] }}</div>
            </div></div>
        @empty
            <div class="card"><p class="text-center text-muted p-4 mb-0">Sin registros de conducta este año.</p></div>
        @endforelse
        @break

    @case('pagos')
        <div class="card mb-3"><div class="card-body fw-bold {{ $datos['pensiones_vencidas'] > 0 ? 'text-danger' : 'text-success' }}">
            @if ($datos['pensiones_vencidas'] > 0)
                <i class="bi bi-exclamation-triangle-fill"></i> {{ $datos['pensiones_vencidas'] }} pensión(es) vencida(s)
            @else
                <i class="bi bi-patch-check-fill"></i> Pensiones al día
            @endif
        </div></div>
        <h2 class="h6 text-uppercase text-muted fw-bold">Pensiones {{ $datos['anio'] }}</h2>
        <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2">
            @foreach ($datos['pensiones'] as $pension)
                <span class="badge {{ ['pagada' => 'bg-success', 'vencida' => 'bg-danger', 'pendiente' => 'bg-secondary'][$pension['estado']] }}">{{ $pension['nombre'] }}: {{ $pension['estado'] }}</span>
            @endforeach
        </div></div>
        <h2 class="h6 text-uppercase text-muted fw-bold">Pagos del año</h2>
        @forelse ($datos['pagos'] as $pago)
            <div class="card mb-2"><div class="card-body py-2 d-flex justify-content-between">
                <div><strong>{{ $pago['detalle'] }}</strong><div class="text-muted small">Recibo N.° {{ $pago['recibo'] }} · {{ \Illuminate\Support\Carbon::parse($pago['fecha'])->format('d/m/Y') }}</div></div>
                <strong class="text-nowrap">S/ {{ number_format($pago['monto'], 2) }}</strong>
            </div></div>
        @empty
            <div class="card"><p class="text-center text-muted p-4 mb-0">Sin pagos registrados este año.</p></div>
        @endforelse
        @break

    @case('comunicados')
        @forelse ($datos['comunicados'] as $comunicado)
            <div class="card mb-2"><div class="card-body">
                <strong>{{ $comunicado['titulo'] }}</strong>
                <div class="text-muted small">{{ \Illuminate\Support\Carbon::parse($comunicado['fecha'])->format('d/m/Y H:i') }}</div>
                <p class="mb-0 mt-1" style="white-space: pre-line">{{ $comunicado['cuerpo'] }}</p>
            </div></div>
        @empty
            <div class="card"><p class="text-center text-muted p-4 mb-0">No hay comunicados.</p></div>
        @endforelse
        @break
@endswitch
@endsection
