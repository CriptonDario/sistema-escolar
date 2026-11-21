@extends('layouts.panel')

@section('titulo', 'Integraciones')
@section('icono', 'bi-plug-fill')
@section('subtitulo', 'Servicios externos opcionales: WhatsApp, Sofía, pagos en línea y facturación electrónica')

@section('contenido')
@php($campo = fn (string $clave, string $defecto = '') => old($clave, $config->texto($clave, $defecto)))
@php($secreto = fn (string $clave) => $config->tieneSecreto($clave) ? 'Guardado. Déjalo vacío para conservarlo' : 'Sin configurar')

<form action="{{ route('integraciones.actualizar') }}" method="POST" autocomplete="off">
    @csrf @method('PUT')

    <div class="row g-3">
        {{-- WhatsApp --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-whatsapp text-success"></i> WhatsApp</span>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="wa_activo" name="wa_activo" value="1" @checked(old('wa_activo', $config->activo('wa_activo')))>
                        <label class="form-check-label fw-normal" for="wa_activo">Activo</label>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Además de la app, cada mensaje al apoderado sale por WhatsApp a su teléfono.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="wa_proveedor">Proveedor</label>
                        <select name="wa_proveedor" id="wa_proveedor" class="form-select">
                            @foreach ($proveedores as $clave => $nombre)
                                <option value="{{ $clave }}" @selected($campo('wa_proveedor', 'ultramsg') === $clave)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="wa_instancia">Instancia</label>
                        <input type="text" name="wa_instancia" id="wa_instancia" class="form-control" maxlength="100" value="{{ $campo('wa_instancia') }}">
                        <div class="form-text">UltraMsg: el "instance id" (instance12345). Meta: el "Phone Number ID".</div>
                    </div>
                    <div>
                        <label class="form-label fw-bold" for="wa_token">Token</label>
                        <input type="password" name="wa_token" id="wa_token" class="form-control" maxlength="500" placeholder="{{ $secreto('wa_token') }}" autocomplete="new-password">
                    </div>
                </div>
            </div>
        </div>

        {{-- Sofía --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-robot text-primary"></i> Sofía</span>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="sofia_activo" name="sofia_activo" value="1" @checked(old('sofia_activo', $config->activo('sofia_activo')))>
                        <label class="form-check-label fw-normal" for="sofia_activo">Activo</label>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted small">La asistente de WhatsApp del colegio. Recibe cada ingreso y salida de los alumnos y le escribe al apoderado.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="sofia_url">Dirección</label>
                        <input type="url" name="sofia_url" id="sofia_url" class="form-control" maxlength="255" value="{{ $campo('sofia_url') }}" placeholder="https://atiendesofia.online/colegio/asistencia">
                    </div>
                    <div>
                        <label class="form-label fw-bold" for="sofia_token">Token</label>
                        <input type="password" name="sofia_token" id="sofia_token" class="form-control" maxlength="500" placeholder="{{ $secreto('sofia_token') }}" autocomplete="new-password">
                    </div>
                    <div class="alert alert-light border small mt-3 mb-0">
                        Si activas WhatsApp y Sofía a la vez, el apoderado recibirá dos avisos por cada ingreso y salida. Normalmente se usa uno de los dos.
                    </div>
                </div>
            </div>
        </div>

        {{-- Pagos en línea --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-credit-card-fill text-primary"></i> Pagos en línea (Mercado Pago)</span>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="mp_activo" name="mp_activo" value="1" @checked(old('mp_activo', $config->activo('mp_activo')))>
                        <label class="form-check-label fw-normal" for="mp_activo">Activo</label>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Con esto activo, los apoderados pueden pagar la matrícula desde la app o el portal (tarjeta, Yape y otros medios de Mercado Pago)
                        y quedan matriculados al instante. El "Access Token" de producción está en la cuenta de Mercado Pago del colegio:
                        Tus integraciones → Credenciales. Sin esto, la matrícula en línea sigue funcionando con pago en secretaría.
                    </p>
                    <div style="max-width: 560px">
                        <label class="form-label fw-bold" for="mp_token">Access Token</label>
                        <input type="password" name="mp_token" id="mp_token" class="form-control" maxlength="500" placeholder="{{ $secreto('mp_token') }}" autocomplete="new-password">
                        <div class="form-text">El sistema debe estar publicado con https para que Mercado Pago avise los pagos automáticamente.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SUNAT --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-text-fill text-secondary"></i> Facturación electrónica (SUNAT, vía Nubefact)</span>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="sunat_activo" name="sunat_activo" value="1" @checked(old('sunat_activo', $config->activo('sunat_activo')))>
                        <label class="form-check-label fw-normal" for="sunat_activo">Activo</label>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Con esto activo, desde el recibo de cada pago se puede emitir su boleta o factura electrónica.
                        La ruta y el token se obtienen en la cuenta de Nubefact del colegio (Configuración → API).
                    </p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold" for="sunat_ruc">RUC del colegio</label>
                            <input type="text" name="sunat_ruc" id="sunat_ruc" class="form-control" maxlength="11" inputmode="numeric" value="{{ $campo('sunat_ruc') }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold" for="sunat_razon_social">Razón social</label>
                            <input type="text" name="sunat_razon_social" id="sunat_razon_social" class="form-control" maxlength="150" value="{{ $campo('sunat_razon_social') }}">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-bold" for="sunat_ruta">Ruta de Nubefact</label>
                            <input type="url" name="sunat_ruta" id="sunat_ruta" class="form-control" maxlength="255" value="{{ $campo('sunat_ruta') }}" placeholder="https://api.nubefact.com/api/v1/…">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold" for="sunat_token">Token de Nubefact</label>
                            <input type="password" name="sunat_token" id="sunat_token" class="form-control" maxlength="500" placeholder="{{ $secreto('sunat_token') }}" autocomplete="new-password">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="sunat_serie_boleta">Serie de boletas</label>
                            <input type="text" name="sunat_serie_boleta" id="sunat_serie_boleta" class="form-control text-uppercase" maxlength="4" required value="{{ $campo('sunat_serie_boleta', 'B001') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="sunat_serie_factura">Serie de facturas</label>
                            <input type="text" name="sunat_serie_factura" id="sunat_serie_factura" class="form-control text-uppercase" maxlength="4" required value="{{ $campo('sunat_serie_factura', 'F001') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="sunat_afectacion">IGV</label>
                            <select name="sunat_afectacion" id="sunat_afectacion" class="form-select">
                                <option value="exonerado" @selected($campo('sunat_afectacion', 'exonerado') === 'exonerado')>Exonerado (servicio educativo)</option>
                                <option value="gravado" @selected($campo('sunat_afectacion', 'exonerado') === 'gravado')>Gravado con 18 %</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-text mt-2">Las series deben ser las mismas que el colegio tiene registradas en Nubefact.</div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg fw-bold mt-3"><i class="bi bi-check-lg"></i> Guardar integraciones</button>
</form>

<div class="card mt-4" style="max-width: 560px">
    <div class="card-header"><i class="bi bi-send text-secondary"></i> Probar WhatsApp</div>
    <div class="card-body">
        <form action="{{ route('integraciones.probar-whatsapp') }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <div class="col">
                <label class="form-label small mb-0" for="telefono">Enviar un mensaje de prueba a</label>
                <input type="tel" name="telefono" id="telefono" class="form-control" maxlength="20" required placeholder="987654321">
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-outline-primary">Enviar prueba</button></div>
        </form>
        <div class="form-text">Guarda primero los cambios: la prueba usa lo que está guardado.</div>
    </div>
</div>
@endsection
