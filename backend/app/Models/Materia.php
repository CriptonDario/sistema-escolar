<?php

namespace App\Models;

use App\Enums\Nivel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    protected $table = 'materias';

    protected $fillable = ['nombre', 'nivel', 'activo'];

    protected function casts(): array
    {
        return ['nivel' => Nivel::class, 'activo' => 'boolean'];
    }

    protected $attributes = ['activo' => true];

    /** Materias que se dictan en el nivel dado (las propias y las de todos los niveles). */
    public function scopeDelNivel(Builder $query, Nivel $nivel): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('nivel')->orWhere('nivel', $nivel->value));
    }
}
