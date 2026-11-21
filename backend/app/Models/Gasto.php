<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    protected $table = 'gastos';

    protected $fillable = ['categoria', 'descripcion', 'monto', 'fecha', 'registrado_por', 'anulado_en'];

    protected function casts(): array
    {
        return ['monto' => 'float', 'fecha' => 'date:Y-m-d', 'anulado_en' => 'datetime'];
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulado_en');
    }
}
