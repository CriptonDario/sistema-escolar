<?php

namespace App\Enums;

/**
 * Qué es cada cuenta dentro del colegio. El rol decide qué rutas puede usar.
 */
enum Rol: string
{
    case Admin = 'admin';
    case Guardia = 'guardia';
    case Direccion = 'direccion';
    case Personal = 'personal';
    case Alumno = 'alumno';
    case Apoderado = 'apoderado';

    /** Cuentas que el colegio crea con la contraseña inicial y deben cambiarla. */
    public function naceConClaveInicial(): bool
    {
        return in_array($this, [self::Personal, self::Alumno, self::Apoderado], true);
    }
}
