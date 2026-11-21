<?php

namespace App\Enums;

enum EstadoAsistencia: string
{
    case Puntual = 'puntual';
    case Tarde = 'tarde';
    case Falta = 'falta';
}
