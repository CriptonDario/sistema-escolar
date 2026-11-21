<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido de matrícula hecho por un apoderado desde la app o el portal.
 * Queda "pendiente" hasta que se registra el pago; ahí pasa a "matriculado".
 */
class SolicitudMatricula extends Model
{
    public const PENDIENTE = 'pendiente';

    public const MATRICULADO = 'matriculado';

    protected $table = 'solicitudes_matricula';

    protected $fillable = [
        'alumno_id', 'apoderado_id', 'grado_id', 'anio', 'monto', 'estado', 'referencia',
        'pasarela_id', 'pago_externo_id', 'pago_id', 'matricula_id',
    ];

    protected $attributes = ['estado' => self::PENDIENTE];

    protected function casts(): array
    {
        return ['anio' => 'integer', 'monto' => 'float'];
    }

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** @return BelongsTo<Apoderado, $this> */
    public function apoderado(): BelongsTo
    {
        return $this->belongsTo(Apoderado::class);
    }

    /** @return BelongsTo<Grado, $this> */
    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }

    public function pendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }
}
