@extends('layouts.base')

@section('titulo', 'Kiosco de asistencia')

@push('estilos')
<style>
    body { background: #111827 !important; }
    .card:hover { transform: none; }
    #resultado { min-height: 150px; transition: background .2s; }
    #resultado.entrada  { background: #198754; color: #fff; }
    #resultado.tardanza { background: #ffc107; color: #212529; }
    #resultado.salida   { background: #0d6efd; color: #fff; }
    #resultado.aviso    { background: #6c757d; color: #fff; }
    #resultado.error    { background: #dc3545; color: #fff; }
    #lector { max-width: 420px; margin: 0 auto; }
</style>
@endpush

@section('cuerpo')
<div class="container py-3" style="max-width: 640px">
    <div class="d-flex justify-content-between align-items-center text-white mb-3">
        <div>
            <div class="fw-bold">{{ $marca->nombre }}</div>
            <small class="text-secondary">Kiosco de asistencia · <span id="reloj"></span></small>
        </div>
        <a href="{{ route('panel') }}" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i> Panel</a>
    </div>

    <div id="resultado" class="card border-0 shadow text-center p-4 mb-3 aviso" role="status" aria-live="polite">
        <div class="h4 fw-bold mb-1" id="resultado-nombre">Acerca el carné a la cámara</div>
        <div id="resultado-detalle">o escribe el código y presiona Enter</div>
    </div>

    <div class="card border-0 shadow mb-3">
        <div class="card-body">
            <form id="form-codigo" class="d-flex gap-2" autocomplete="off">
                <input type="text" id="codigo" class="form-control form-control-lg" placeholder="Código del carné"
                       maxlength="20" aria-label="Código del carné" autofocus>
                <button type="submit" class="btn btn-primary btn-lg fw-bold">Marcar</button>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow">
        <div class="card-body">
            <div id="lector"></div>
            <p class="text-muted small text-center mb-0 mt-2" id="estado-camara">Iniciando cámara…</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/html5-qrcode.min.js') }}"></script>
<script>
const URL_MARCAR = @json(route('kiosco.marcar'));
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

const caja = document.getElementById('resultado');
const cajaNombre = document.getElementById('resultado-nombre');
const cajaDetalle = document.getElementById('resultado-detalle');

function mostrar(clase, titulo, detalle) {
    caja.className = 'card border-0 shadow text-center p-4 mb-3 ' + clase;
    cajaNombre.textContent = titulo;
    cajaDetalle.textContent = detalle || '';
}

// La cámara lee el mismo QR muchas veces por segundo: se ignora el mismo
// código durante unos segundos y nunca se mandan dos peticiones a la vez.
let enVuelo = false, ultimoCodigo = '', ultimoMomento = 0;

async function marcar(codigo) {
    codigo = codigo.trim();
    const ahora = Date.now();
    if (!codigo || enVuelo || (codigo === ultimoCodigo && ahora - ultimoMomento < 4000)) return;
    enVuelo = true; ultimoCodigo = codigo; ultimoMomento = ahora;

    try {
        const r = await fetch(URL_MARCAR, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ codigo: codigo }),
            credentials: 'same-origin',
        });

        // Sesión vencida tras horas con el kiosco abierto: recargar lleva al login.
        if (r.status === 401 || r.status === 419) { location.reload(); return; }

        const datos = await r.json();

        if (!r.ok) {
            mostrar('error', datos.message || 'No se pudo registrar.', codigo);
        } else {
            const clase = datos.registrado ? datos.evento : 'aviso';
            const hora = datos.evento === 'salida' ? 'Salida ' + datos.salida : 'Entrada ' + datos.entrada;
            mostrar(clase, datos.mensaje, [datos.detalle, hora].filter(Boolean).join(' · '));
        }
    } catch (e) {
        mostrar('error', 'Sin conexión con el servidor.', 'Vuelve a pasar el carné.');
        ultimoCodigo = '';
    } finally {
        enVuelo = false;
    }
}

document.getElementById('form-codigo').addEventListener('submit', function (ev) {
    ev.preventDefault();
    const campo = document.getElementById('codigo');
    ultimoCodigo = '';               // lo tecleado a mano siempre se envía
    marcar(campo.value);
    campo.value = '';
    campo.focus();
});

setInterval(function () {
    document.getElementById('reloj').textContent = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
}, 1000);

// Mantiene viva la sesión mientras la pantalla esté abierta.
setInterval(function () { fetch(@json(route('kiosco')), { credentials: 'same-origin' }); }, 5 * 60 * 1000);

const estadoCamara = document.getElementById('estado-camara');
if (window.Html5Qrcode) {
    const lector = new Html5Qrcode('lector');
    lector.start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, marcar)
        .then(function () { estadoCamara.textContent = 'Cámara lista.'; })
        .catch(function () { estadoCamara.textContent = 'No se pudo usar la cámara. Puedes escribir el código.'; });
} else {
    estadoCamara.textContent = 'Lector de cámara no disponible. Escribe el código.';
}
</script>
@endpush
