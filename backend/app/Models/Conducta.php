<?php

namespace App\Models;

use App\Enums\TipoConducta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conducta extends Model
{
    protected $table = 'conductas';

    protected $fillable = ['alumno_id', 'tipo', 'descripcion', 'fecha', 'registrado_por', 'anulado_en'];

    protected function casts(): array
    {
        return ['tipo' => TipoConducta::class, 'fecha' => 'date:Y-m-d', 'anulado_en' => 'datetime'];
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
}
