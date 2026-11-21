{{-- Carné del alumno. Espera $alumno con seccion.grado y apoderados ya cargados. --}}
@include('carnes.tarjeta', [
    'titulo' => 'CARNÉ DE ALUMNO',
    'nombre' => $alumno->apellidos.' '.$alumno->nombres,
    'iniciales' => mb_strtoupper(mb_substr($alumno->nombres, 0, 1).mb_substr($alumno->apellidos, 0, 1)),
    'foto' => $alumno->foto ? route('alumnos.foto', $alumno).'?v='.$alumno->updated_at?->timestamp : null,
    'codigo' => $alumno->codigo,
    'anverso' => [
        'Grado y sección' => $alumno->aula(),
        'Documento de identidad' => $alumno->dni,
    ],
    'reverso' => [
        'Apoderado' => mb_strtoupper($alumno->apoderados->pluck('nombres')->join(' - ')),
        'Teléfono de contacto' => $alumno->apoderados->first(fn ($apoderado) => filled($apoderado->telefono))?->telefono,
    ],
])
