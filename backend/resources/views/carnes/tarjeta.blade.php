{{-- El carné del colegio: anverso y reverso, tamaño DNI. Lo usan alumnos,
     docentes y trabajadores (ver carnes/alumno y carnes/personal).

     $titulo    "CARNÉ DE ALUMNO"
     $nombre    apellidos y nombres
     $iniciales las que van si no hay foto
     $foto      dirección de la foto, o null
     $codigo    lo que lleva el QR
     $anverso   [etiqueta => valor] bajo el nombre
     $reverso   [etiqueta => valor]
     Opcionales: $etiquetaNombre (por defecto "Apellidos y nombres") y $aviso (el texto del reverso). --}}
@php($marcaDeAgua = $marca->logo ? "--carne-marca: url('".asset($marca->logo)."')" : '')

@once
    @push('estilos')
        <link rel="stylesheet" href="{{ asset('css/carne.css') }}?v={{ filemtime(public_path('css/carne.css')) }}">
    @endpush
@endonce

<div class="carnes">
    <div class="carne" style="{{ $marcaDeAgua }}">
        <div class="carne-cab">
            <div class="carne-logo">
                @if ($marca->logo)<img src="{{ asset($marca->logo) }}" alt="">@else<i class="bi bi-mortarboard-fill"></i>@endif
            </div>
            <div class="carne-titulo">
                <b>{{ $titulo }}</b>
                <small>{{ $marca->nombre }} · Control de acceso</small>
            </div>
            <div class="carne-anio"><span>AÑO</span>{{ now()->year }}</div>
        </div>
        <div class="carne-cuerpo">
            @if ($foto)
                <div class="carne-foto"><img src="{{ $foto }}" alt="Foto de {{ $nombre }}"></div>
            @else
                <div class="carne-foto" aria-hidden="true">{{ $iniciales }}</div>
            @endif
            <dl class="carne-datos">
                <dt>{{ $etiquetaNombre ?? 'Apellidos y nombres' }}</dt>
                <dd>{{ mb_strtoupper($nombre) }}</dd>
                @foreach ($anverso as $etiqueta => $valor)
                    <dt>{{ $etiqueta }}</dt>
                    <dd>{{ filled($valor) ? $valor : '—' }}</dd>
                @endforeach
            </dl>
            <div class="carne-qr">
                <div role="img" aria-label="Código QR del carné {{ $codigo }}">{!! \App\Support\CodigoQr::svg($codigo) !!}</div>
                <span class="carne-codigo">{{ $codigo }}</span>
            </div>
        </div>
    </div>

    <div class="carne" style="{{ $marcaDeAgua }}">
        <div class="carne-cab">
            <div class="carne-logo">
                @if ($marca->logo)<img src="{{ asset($marca->logo) }}" alt="">@else<i class="bi bi-mortarboard-fill"></i>@endif
            </div>
            <div class="carne-titulo">
                <b>{{ $marca->nombre }}</b>
                <small>Información adicional</small>
            </div>
        </div>
        <div class="carne-cuerpo reverso">
            <div class="carne-aviso">
                <i class="bi bi-exclamation-triangle"></i>
                {{ $aviso ?? 'Este carné es personal e intransferible. Si lo encuentra, comuníquese con '.$marca->nombre.'.' }}
            </div>
            <dl class="carne-datos">
                @foreach ($reverso as $etiqueta => $valor)
                    <dt>{{ $etiqueta }}</dt>
                    <dd>{{ filled($valor) ? $valor : '—' }}</dd>
                @endforeach
            </dl>
            <div class="carne-firma">Firma autorizada — {{ $marca->nombre }}</div>
        </div>
    </div>
</div>
