<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una boleta o factura electrónica emitida por un pago.
 */
class Comprobante extends Model
{
    public const BOLETA = 'boleta';

    public const FACTURA = 'factura';

    public const PENDIENTE = 'pendiente';

    public const ACEPTADO = 'aceptado';

    public const RECHAZADO = 'rechazado';

    public const ERROR = 'error';

    protected $table = 'comprobantes';

    protected $fillable = [
        'pago_id', 'tipo', 'serie', 'correlativo', 'cliente_tipo_doc', 'cliente_numero_doc', 'cliente_nombre',
        'total', 'estado', 'mensaje_sunat', 'enlace_pdf', 'emitido_por',
    ];

    protected $attributes = ['estado' => self::PENDIENTE, 'mensaje_sunat' => null, 'enlace_pdf' => null];

    protected function casts(): array
    {
        return ['total' => 'float', 'correlativo' => 'integer'];
    }

    /** @return BelongsTo<Pago, $this> */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    /** "B001-00000012" */
    public function numero(): string
    {
        return $this->serie.'-'.str_pad((string) $this->correlativo, 8, '0', STR_PAD_LEFT);
    }

    /** Se puede volver a enviar si SUNAT aún no lo aceptó. */
    public function sePuedeReenviar(): bool
    {
        return in_array($this->estado, [self::PENDIENTE, self::ERROR], true);
    }
}
