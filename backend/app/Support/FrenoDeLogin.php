<?php

namespace App\Support;

/**
 * Segundo freno del inicio de sesión, por dirección IP.
 *
 * El primero es por cuenta (5 fallos la bloquean un rato). Este frena otra
 * cosa: a quien prueba una misma contraseña —por ejemplo la inicial— contra
 * muchas cuentas distintas. Es holgado a propósito, porque todo un colegio
 * sale a internet con la misma dirección.
 */
class FrenoDeLogin
{
    public const FALLOS_POR_IP = 40;

    public const VENTANA_SEGUNDOS = 600;

    public static function clave(?string $ip): string
    {
        return 'login-ip:'.($ip ?? 'desconocida');
    }
}
