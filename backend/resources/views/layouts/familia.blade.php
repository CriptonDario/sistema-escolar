@extends('layouts.base')

@section('cuerpo')
<nav class="navbar sticky-top" style="background: linear-gradient(120deg, var(--col-primario), var(--col-primario-osc))">
    <div class="container" style="max-width: 860px">
        <a href="{{ route('familia.inicio') }}" class="navbar-brand text-white fw-bold d-flex align-items-center gap-2 m-0">
            @if ($marca->logo)
                <span class="bg-white rounded-2 p-1 d-inline-flex"><img src="{{ asset($marca->logo) }}" alt="" style="height: 30px"></span>
            @endif
            <span style="font-size: 1rem">{{ $marca->nombre }}</span>
        </a>
        <a href="{{ route('familia.matricula') }}" class="btn btn-sm btn-outline-light fw-bold ms-auto me-2"><i class="bi bi-journal-bookmark-fill"></i> Matrícula</a>
        <form action="{{ route('logout') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-light fw-bold"><i class="bi bi-box-arrow-right"></i> Salir</button>
        </form>
    </div>
</nav>

<main class="container py-3" style="max-width: 860px">
    @if (session('ok'))
        <div class="alert alert-success">{{ session('ok') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @yield('contenido')
</main>
@endsection
