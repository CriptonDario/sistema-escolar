<?php

namespace App\Models;

use App\Enums\TipoPermiso;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permiso extends Model
{
    protected $table = 'permisos';

    protected $fillable = ['alumno_id', 'tipo', 'fecha', 'hora', 'motivo', 'registrado_por', 'anulado_en'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoPermiso::class,
            'fecha' => 'date:Y-m-d',
            'anulado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulado_en');
    }

    public function estaVigente(): bool
    {
        return $this->anulado_en === null;
    }
}
