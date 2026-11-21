<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un aula: la sección A, B, C… de un grado. A ella pertenecen los alumnos.
 */
class Seccion extends Model
{
    use HasFactory;

    protected $table = 'secciones';

    protected $fillable = ['grado_id', 'nombre', 'activo'];

    /** Valores con los que nace el registro (los mismos que los de la tabla). */
    protected $attributes = ['activo' => true];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** @return BelongsTo<Grado, $this> */
    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }

    /** @return HasMany<Alumno, $this> */
    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class);
    }

    /** "1ro Primaria A" */
    public function nombreCompleto(): string
    {
        return trim($this->grado->nombre.' '.$this->nombre);
    }
}
