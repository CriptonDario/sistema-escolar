{{-- Menú del panel. Se imprime dos veces: en la barra lateral de escritorio y
     en el panel deslizable del móvil.

     Las secciones son plegables: abierta queda la de la pantalla en la que
     estás, así el menú completo cabe sin tener que desplazarlo. --}}
@php
    $rol = auth()->user()->rol;
    $esAdmin = $rol === \App\Enums\Rol::Admin;
    $puerta = in_array($rol, [\App\Enums\Rol::Admin, \App\Enums\Rol::Guardia], true);
    $consulta = in_array($rol, [\App\Enums\Rol::Admin, \App\Enums\Rol::Direccion], true);

    // [ruta, patrón que la marca como activa, icono, texto, ¿se muestra?]
    $secciones = [
        'Asistencia y acceso' => [
            ['visitas.index', 'visitas.*', 'bi-person-vcard', 'Visitantes', $puerta],
            ['incidencias.index', 'incidencias.*', 'bi-journal-text', 'Bitácora', true],
            ['historial.index', 'historial.*', 'bi-clock-history', 'Historial de asistencia', $consulta],
            ['permisos.index', 'permisos.*', 'bi-card-checklist', 'Permisos', $esAdmin],
            ['apoderados.index', 'apoderados.*', 'bi-people-fill', 'Apoderados (app)', $esAdmin],
        ],
        'Gestión académica' => [
            ['alumnos.index', 'alumnos.*', 'bi-mortarboard', 'Alumnos', $esAdmin],
            ['personal.index', 'personal.*', 'bi-person-badge', 'Personal', $esAdmin],
            ['grados.index', 'grados.*', 'bi-diagram-3-fill', 'Grados y secciones', $esAdmin],
            ['matriculas.index', 'matriculas.*', 'bi-journal-bookmark-fill', 'Matrículas', $esAdmin],
            ['materias.index', 'materias.*', 'bi-book-fill', 'Materias', $esAdmin],
            ['asignaciones.index', 'asignaciones.*', 'bi-person-workspace', 'Asig. docentes', $esAdmin],
            ['notas.index', 'notas.*', 'bi-journal-check', 'Notas', $esAdmin],
            ['horarios.index', 'horarios.*', 'bi-calendar-week', 'Horarios', $esAdmin],
            ['tareas.index', 'tareas.*', 'bi-list-task', 'Tareas y agenda', $esAdmin],
            ['conducta.index', 'conducta.*', 'bi-award-fill', 'Conducta', $esAdmin],
            ['comunicados.index', 'comunicados.*', 'bi-megaphone-fill', 'Comunicados', $esAdmin],
        ],
        'Administración' => [
            ['pagos.index', 'pagos.*', 'bi-cash-coin', 'Pagos', $esAdmin],
            ['conceptos.index', 'conceptos.*', 'bi-tags-fill', 'Conceptos de pago', $esAdmin],
            ['gastos.index', 'gastos.*', 'bi-receipt', 'Gastos', $esAdmin],
            ['inventario.index', 'inventario.*', 'bi-box-seam-fill', 'Inventario', $esAdmin],
        ],
        'Sistema' => [
            ['usuarios.index', 'usuarios.*', 'bi-person-gear', 'Usuarios del sistema', $esAdmin],
            ['integraciones.editar', 'integraciones.*', 'bi-plug-fill', 'Integraciones', $esAdmin],
            ['seguridad.index', 'seguridad.*', 'bi-shield-lock-fill', 'Seguridad', $esAdmin],
            ['ajustes.editar', 'ajustes.*', 'bi-gear-fill', 'Configuración', $esAdmin],
        ],
    ];
@endphp
<ul>
    <li><a href="{{ route('panel') }}" class="{{ request()->routeIs('panel') ? 'active' : '' }}"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
    @if ($puerta)
        <li><a href="{{ route('kiosco') }}" class="destacado"><i class="bi bi-qr-code-scan"></i> Abrir Kiosco</a></li>
    @endif
</ul>

@foreach ($secciones as $titulo => $enlaces)
    @php($visibles = array_filter($enlaces, fn ($enlace) => $enlace[4]))
    @continue($visibles === [])
    @php($abierta = collect($visibles)->contains(fn ($enlace) => request()->routeIs($enlace[1])))
    <details class="grupo-menu" @if ($abierta) open @endif>
        <summary>{{ $titulo }} <span class="cuenta">{{ count($visibles) }}</span><i class="bi bi-chevron-down flecha"></i></summary>
        <ul>
            @foreach ($visibles as [$ruta, $patron, $icono, $texto])
                <li><a href="{{ route($ruta) }}" class="{{ request()->routeIs($patron) ? 'active' : '' }}"><i class="bi {{ $icono }}"></i> {{ $texto }}</a></li>
            @endforeach
        </ul>
    </details>
@endforeach

<ul class="mt-2 border-top border-secondary pt-2">
    <li><a href="#" data-bs-toggle="modal" data-bs-target="#modal-mi-clave" class="text-info"><i class="bi bi-key"></i> Cambiar mi clave</a></li>
    <li>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <a href="#" class="text-danger" onclick="this.closest('form').submit(); return false;"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
        </form>
    </li>
</ul>
