@extends('layouts.panel')

@section('titulo', 'Estado de cuenta '.$estado['anio'])
@section('icono', 'bi-clipboard-data')
@section('subtitulo', $alumno->nombreCompleto().' · '.($alumno->aula() ?? 'Sin aula'))

@section('acciones')
    <a href="{{ route('pagos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Pagos</a>
@endsection

@section('contenido')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Pensiones {{ $estado['anio'] }}</div>
            <ul class="list-group list-group-flush">
                @foreach ($estado['pensiones'] as $pension)
                    <li class="list-group-item d-flex justify-content-between bg-transparent">
                        <span>{{ $pension['nombre'] }}</span>
                        <span class="badge {{ ['pagada' => 'bg-success', 'vencida' => 'bg-danger', 'pendiente' => 'bg-secondary'][$pension['estado']] }}">{{ ucfirst($pension['estado']) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="card-footer bg-white">
                @if ($estado['pensiones_vencidas'] > 0)
                    <span class="text-danger fw-bold">{{ $estado['pensiones_vencidas'] }} pensión(es) vencida(s)</span>
                @else
                    <span class="text-success fw-bold">Al día</span>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Pagos del año</span><strong>S/ {{ number_format($estado['total_pagado'], 2) }}</strong>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th class="ps-3">Recibo</th><th>Concepto</th><th>Fecha</th><th class="text-end pe-3">Monto</th></tr></thead>
                    <tbody>
                    @forelse ($estado['pagos'] as $pago)
                        <tr>
                            <td class="ps-3">N.° {{ $pago['recibo'] }}</td>
                            <td>{{ $pago['detalle'] }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($pago['fecha'])->format('d/m/Y') }}</td>
                            <td class="text-end pe-3">S/ {{ number_format($pago['monto'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted p-4">Sin pagos registrados este año.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
