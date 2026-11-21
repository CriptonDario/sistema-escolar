<?php

namespace App\Enums;

/** A quién le llega un comunicado. */
enum AlcanceComunicado: string
{
    case Todos = 'todos';
    case Nivel = 'nivel';
    case Seccion = 'seccion';
}
