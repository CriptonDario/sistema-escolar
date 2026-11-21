@extends('layouts.base')

@section('titulo', 'Iniciar sesión')

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
@endpush

@section('cuerpo')
<div class="login-shell">

    {{-- Panel de marca (solo en pantallas anchas) --}}
    <div class="login-pano">
        <div class="pano-blob b1"></div>
        <div class="pano-blob b2"></div>
        <div class="pano-blob b3"></div>
        <div class="login-pano-inner">
            @if ($marca->logo)
                <img class="escudo" src="{{ asset($marca->logo) }}" alt="{{ $marca->nombre }}">
            @else
                <i class="bi bi-mortarboard-fill sin-escudo"></i>
            @endif
            <h2>{{ $marca->nombre }}</h2>
            <div class="pano-sub">Asistencia y control de acceso, en tiempo real.</div>
            <div class="pano-feature"><i class="bi bi-qr-code-scan"></i> Entradas y salidas con carné QR en la puerta</div>
            <div class="pano-feature"><i class="bi bi-phone"></i> Mensajes a los apoderados en la app del colegio</div>
            <div class="pano-feature"><i class="bi bi-graph-up-arrow"></i> Reportes y asistencia del día en un vistazo</div>
        </div>
    </div>

    {{-- Formulario --}}
    <div class="login-lado">
        <div class="login-wrap">
            <div class="login-brand">
                @if ($marca->logo)
                    <img src="{{ asset($marca->logo) }}" alt="{{ $marca->nombre }}">
                @endif
                <h1>Iniciar sesión</h1>
                <p>Portal institucional</p>
            </div>

            <div class="card card-login">
                <div class="card-body p-4">
                    @if ($errors->any())
                        <div class="alert alert-danger text-center p-2 mb-3 small rounded-3">
                            <i class="bi bi-exclamation-circle-fill me-1"></i> {{ $errors->first() }}
                        </div>
                    @endif

                    <form action="{{ route('login') }}" method="POST">
                        @csrf
                        <div class="form-floating mb-3">
                            <input type="text" name="usuario" id="usuario" class="form-control" value="{{ old('usuario') }}"
                                   placeholder="Usuario o teléfono" autocomplete="username" maxlength="120"
                                   autocapitalize="none" autocorrect="off" spellcheck="false" required autofocus>
                            <label for="usuario"><i class="bi bi-person me-1"></i> Usuario o teléfono</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" name="clave" id="clave" class="form-control"
                                   placeholder="Contraseña" autocomplete="current-password" maxlength="100" required>
                            <label for="clave"><i class="bi bi-lock me-1"></i> Contraseña</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Padres y apoderados: entren con su teléfono, después de haber creado su contraseña en la app.
                            <br>¿Problemas para entrar? Consulta en secretaría del colegio.
                        </small>
                    </div>
                </div>
            </div>

            <div class="login-footer">
                <small>{{ $marca->nombre }} &copy; {{ date('Y') }}</small>
            </div>
        </div>
    </div>
</div>
@endsection
