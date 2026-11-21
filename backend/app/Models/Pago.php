<?php

namespace App\Models;

use App\Enums\MetodoPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pago extends Model
{
    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    protected $table = 'pagos';

    protected $fillable = [
        'alumno_id', 'concepto_id', 'anio', 'mes', 'monto', 'fecha', 'metodo',
        'numero_operacion', 'observacion', 'registrado_por', 'anulado_en',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'float', 'fecha' => 'date:Y-m-d', 'metodo' => MetodoPago::class,
            'anio' => 'integer', 'mes' => 'integer', 'anulado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** @return BelongsTo<ConceptoPago, $this> */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoPago::class, 'concepto_id');
    }

    /** @return HasMany<Comprobante, $this> */
    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class)->latest('id');
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulado_en');
    }

    public function estaVigente(): bool
    {
        return $this->anulado_en === null;
    }

    /** Número de recibo: el id con ceros a la izquierda. */
    public function numeroRecibo(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** "Pensión — Marzo 2026" o "Matrícula 2026". */
    public function detalle(): string
    {
        return $this->concepto->nombre.($this->mes ? ' — '.self::MESES[$this->mes] : '').' '.$this->anio;
    }
}
