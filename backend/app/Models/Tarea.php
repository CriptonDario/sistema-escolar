<?php

namespace App\Models;

use App\Enums\TipoTarea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarea extends Model
{
    protected $table = 'tareas';

    protected $fillable = ['seccion_id', 'materia_id', 'tipo', 'titulo', 'descripcion', 'fecha_entrega', 'publicado_por', 'anulado_en'];

    protected function casts(): array
    {
        return ['tipo' => TipoTarea::class, 'fecha_entrega' => 'date:Y-m-d', 'anulado_en' => 'datetime'];
    }

    protected $attributes = ['tipo' => 'tarea'];

    /** @return BelongsTo<Seccion, $this> */
    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    /** @return BelongsTo<Materia, $this> */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulado_en');
    }
}
