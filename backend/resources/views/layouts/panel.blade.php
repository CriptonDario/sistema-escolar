@extends('layouts.base')

@section('cuerpo')
@php($usuario = auth()->user())

{{-- Cerrar sesión fijo arriba a la derecha (escritorio). --}}
<div class="topbar-logout">
    <span class="topbar-logout__user">
        <i class="bi bi-person-circle fs-5 text-secondary align-middle me-1"></i>
        {{ $usuario->usuario }}
        <small>{{ $usuario->rol->value }}</small>
    </span>
    <form action="{{ route('logout') }}" method="POST" class="m-0">
        @csrf
        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</button>
    </form>
</div>

<div class="d-flex flex-column flex-md-row">

    {{-- Menú lateral de escritorio --}}
    <div class="sidebar d-none d-md-block" style="max-height: 100vh; overflow-y: auto; position: sticky; top: 0;">
        <div class="marca">
            @if ($marca->logo)
                <div class="logo"><img src="{{ asset($marca->logo) }}" alt="{{ $marca->nombre }}"></div>
            @else
                <i class="bi bi-mortarboard-fill fs-2 d-block mb-1"></i>
            @endif
            <h5>{{ $marca->nombre }}</h5>
            <small>Intranet — {{ $usuario->rol->value }}</small>
        </div>
        @include('layouts.menu')
    </div>

    {{-- Barra superior del móvil --}}
    <div class="d-md-none w-100">
        <nav class="navbar navbar-dark bg-dark shadow p-3">
            <div class="container-fluid p-0">
                <button class="btn border-0 text-white" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu-movil" aria-label="Abrir menú">
                    <i class="bi bi-list fs-1"></i>
                </button>
                <span class="navbar-brand m-0 fw-bold text-white"><i class="bi bi-mortarboard-fill text-warning"></i> {{ $marca->nombre }}</span>
                <span style="width: 3rem"></span>
            </div>
        </nav>
    </div>
    <div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="menu-movil" style="width: 280px">
        <div class="offcanvas-header border-bottom border-secondary">
            <h5 class="offcanvas-title fw-bold"><i class="bi bi-person-circle me-2"></i>{{ $usuario->nombre }}</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body p-0 sidebar" style="width: 100%; flex: 1 1 auto; min-height: 0">
            @include('layouts.menu')
        </div>
    </div>

    <main class="flex-grow-1 p-3 p-md-4" style="min-width: 0">
        @hasSection('cabecera')
            @yield('cabecera')
        @else
            <div class="page-header">
                <div class="ph-titulo">
                    <div class="ph-icono"><i class="bi @yield('icono', 'bi-grid')"></i></div>
                    <div>
                        <h2>@yield('titulo')</h2>
                        @hasSection('subtitulo')<div class="ph-sub">@yield('subtitulo')</div>@endif
                    </div>
                </div>
                <div>@yield('acciones')</div>
            </div>
        @endif

        @if (session('ok'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('ok') }} <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }} <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('contenido')
    </main>
</div>

<div class="modal fade" id="modal-mi-clave" tabindex="-1" aria-labelledby="titulo-mi-clave" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="titulo-mi-clave"><i class="bi bi-shield-lock"></i> Cambiar mi contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form action="{{ route('mi-cuenta.clave') }}" method="POST" autocomplete="off">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="mi_clave_actual">Contraseña actual</label>
                        <input type="password" name="clave_actual" id="mi_clave_actual" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="mi_clave_nueva">Nueva contraseña</label>
                        <input type="password" name="clave_nueva" id="mi_clave_nueva" class="form-control" required minlength="8" maxlength="100" autocomplete="new-password">
                        <div class="form-text">Mínimo 8 caracteres, con mayúsculas, minúsculas y números.</div>
                    </div>
                    <div>
                        <label class="form-label" for="mi_clave_confirmacion">Repite la nueva contraseña</label>
                        <input type="password" name="clave_nueva_confirmation" id="mi_clave_confirmacion" class="form-control" required minlength="8" maxlength="100" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
