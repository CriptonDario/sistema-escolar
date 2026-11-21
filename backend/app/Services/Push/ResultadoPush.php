<?php

namespace App\Services\Push;

enum ResultadoPush
{
    case Enviado;
    /** El teléfono desinstaló la app o renovó su token: hay que olvidarlo. */
    case TokenInvalido;
    case Error;
}
