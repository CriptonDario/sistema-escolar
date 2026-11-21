<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Panel') — {{ $marca->nombre }}</title>
    @if ($marca->logo)
        <link rel="icon" href="{{ asset($marca->logo) }}">
    @endif
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    {{-- Color institucional del colegio: tiñe botones, menú y acentos. --}}
    <style>
        :root {
            --col-primario: {{ $marca->color }};
            --col-primario-osc: {{ $marca->colorOscuro() }};
            --col-primario-rgb: {{ $marca->colorRgb() }};
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/tema.css') }}?v={{ filemtime(public_path('css/tema.css')) }}">
    @stack('estilos')
</head>
<body>
@yield('cuerpo')
<script src="{{ asset('vendor/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
