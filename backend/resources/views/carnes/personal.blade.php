{{-- Carné de un docente o trabajador. Espera $persona (Personal). --}}
@include('carnes.tarjeta', [
    'titulo' => $persona->tituloDelCarne(),
    'nombre' => $persona->apellidos.' '.$persona->nombres,
    'iniciales' => mb_strtoupper(mb_substr($persona->nombres, 0, 1).mb_substr($persona->apellidos, 0, 1)),
    'foto' => $persona->foto ? route('personal.foto', $persona).'?v='.$persona->updated_at?->timestamp : null,
    'codigo' => $persona->codigo,
    'anverso' => [
        'Cargo' => $persona->cargo ?: $persona->tipo->etiqueta(),
        'Documento de identidad' => $persona->dni,
    ],
    'reverso' => [
        'Personal' => $persona->tipo->etiqueta(),
        'Cargo' => $persona->cargo,
    ],
])
