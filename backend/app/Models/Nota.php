<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nota extends Model
{
    protected $table = 'notas';

    protected $fillable = ['alumno_id', 'materia_id', 'anio', 'periodo', 'nota', 'letra', 'observacion', 'registrado_por'];

    protected $attributes = ['nota' => null, 'letra' => null];

    protected function casts(): array
    {
        return ['nota' => 'float', 'anio' => 'integer', 'periodo' => 'integer'];
    }

    /** La calificación tal como se muestra: la letra o el número. */
    public function valor(): string|float|null
    {
        return $this->letra ?? $this->nota;
    }

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** @return BelongsTo<Materia, $this> */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }
}
