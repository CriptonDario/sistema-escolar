<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un docente a cargo de una materia en un aula, durante un año escolar.
 */
class Asignacion extends Model
{
    protected $table = 'asignaciones';

    protected $fillable = ['personal_id', 'seccion_id', 'materia_id', 'anio'];

    protected function casts(): array
    {
        return ['anio' => 'integer'];
    }

    /** @return BelongsTo<Personal, $this> */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

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
}
