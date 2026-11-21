<?php

namespace App\Enums;

enum MetodoPago: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Yape = 'yape';
    case Plin = 'plin';
    case Tarjeta = 'tarjeta';
    // Lo registra el sistema cuando la pasarela confirma un pago en línea.
    case EnLinea = 'en_linea';

    public function etiqueta(): string
    {
        return $this === self::EnLinea ? 'En línea' : ucfirst($this->value);
    }

    /**
     * Los que elige quien cobra en caja.
     *
     * @return array<int, self>
     */
    public static function deCaja(): array
    {
        return [self::Efectivo, self::Transferencia, self::Yape, self::Plin, self::Tarjeta];
    }
}
