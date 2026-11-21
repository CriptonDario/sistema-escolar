<?php

namespace App\Models;

use App\Enums\Nivel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grado extends Model
{
    use HasFactory;

    protected $table = 'grados';

    protected $fillable = ['nivel', 'nombre', 'orden', 'activo'];

    /** Valores con los que nace el registro (los mismos que los de la tabla). */
    protected $attributes = ['activo' => true, 'orden' => 0];

    protected function casts(): array
    {
        return [
            'nivel' => Nivel::class,
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<Seccion, $this> */
    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class)->orderBy('nombre');
    }

    public function tieneAlumnos(): bool
    {
        return $this->secciones()->whereHas('alumnos')->exists();
    }

    /** Inicial, primaria, secundaria; y dentro de cada nivel por su orden. */
    public function scopeOrdenados(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE nivel WHEN 'inicial' THEN 1 WHEN 'primaria' THEN 2 ELSE 3 END")
            ->orderBy('orden')
            ->orderBy('nombre');
    }
}
