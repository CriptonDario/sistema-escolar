@extends('layouts.base')

@section('titulo', 'Pago de matrícula')

@section('cuerpo')
<main class="container py-5" style="max-width: 520px">
    <div class="card text-center">
        <div class="card-body p-4">
            @if ($marca->logo)
                <img src="{{ asset($marca->logo) }}" alt="" style="height: 72px" class="mb-3">
            @endif
            @if ($confirmada)
                <div class="text-success" style="font-size: 3rem"><i class="bi bi-patch-check-fill"></i></div>
                <h1 class="h4 fw-bold">Pago recibido</h1>
                <p>La matrícula quedó confirmada. Te llegó un mensaje con el detalle.</p>
            @else
                <div class="text-warning" style="font-size: 3rem"><i class="bi bi-hourglass-split"></i></div>
                <h1 class="h4 fw-bold">Todavía no vemos el pago</h1>
                <p>Si pagaste, puede tardar unos minutos en confirmarse: te llegará un mensaje. Si no pudiste pagar, vuelve a intentarlo o paga en secretaría.</p>
            @endif
            <p class="text-muted small mb-3">{{ $marca->nombre }}</p>
            <a href="{{ route('familia.matricula') }}" class="btn btn-primary fw-bold">Ver la matrícula</a>
            <div class="text-muted small mt-2">Si usas la app del colegio, ya puedes volver a ella.</div>
        </div>
    </div>
</main>
@endsection
