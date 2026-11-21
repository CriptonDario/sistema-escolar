<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * El código escaneado no corresponde a nadie activo, o es ambiguo.
 * Se responde 422 con un texto que el guardia puede leer en pantalla.
 */
class CodigoNoReconocido extends RuntimeException
{
    public static function inexistente(): self
    {
        return new self('Código no reconocido. No corresponde a un alumno ni a personal activo.');
    }

    public static function repetido(string $codigo): self
    {
        return new self("El código {$codigo} está repetido en alumnos y personal. Avisa a administración.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'codigo' => 'codigo_no_reconocido'], 422);
    }
}
