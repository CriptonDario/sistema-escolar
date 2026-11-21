<?php

namespace App\Enums;

/** Clase de mensaje que recibe un apoderado en la app. */
enum TipoMensaje: string
{
    case Entrada = 'entrada';
    case Tardanza = 'tardanza';
    case Salida = 'salida';
    case Permiso = 'permiso';
    case Comunicado = 'comunicado';
    case Tarea = 'tarea';
    case Conducta = 'conducta';
    case Nota = 'nota';
    case Pago = 'pago';
}
