{{-- Pase genérico de visitante: no lleva nombre, solo su número. Espera $codigo ("VIS-01"). --}}
@include('carnes.tarjeta', [
    'titulo' => 'PASE DE VISITANTE',
    'etiquetaNombre' => 'Tipo',
    'nombre' => 'Visitante',
    'iniciales' => substr($codigo, -2),
    'foto' => null,
    'codigo' => $codigo,
    'anverso' => [
        'Pase N.°' => substr($codigo, -2),
        'Válido' => 'Solo durante la visita',
    ],
    'aviso' => 'Este pase es del colegio y no acredita identidad. Si lo encuentra, comuníquese con '.$marca->nombre.'.',
    'reverso' => [
        'Indicaciones' => 'Llévelo visible mientras esté en el colegio.',
        'Al salir' => 'Devuélvalo en la puerta.',
    ],
])
