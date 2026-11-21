<?php

namespace App\Enums;

enum EstadoMatricula: string
{
    case Matriculado = 'matriculado';
    case Retirado = 'retirado';
    case Trasladado = 'trasladado';
}
