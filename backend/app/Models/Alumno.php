<?php

namespace App\Models;

use App\Models\Concerns\RegistraAsistencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alumno extends Model
{
    use HasFactory, RegistraAsistencia;

    protected $table = 'alumnos';

    protected $fillable = [
        'user_id', 'seccion_id', 'codigo', 'nombres', 'apellidos', 'dni', 'fecha_nacimiento', 'foto', 'activo',
    ];

    /** Valores con los que nace el registro (los mismos que los de la tabla). */
    protected $attributes = ['activo' => true];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Seccion, $this> */
    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    /** @return BelongsToMany<Apoderado, $this> */
    public function apoderados(): BelongsToMany
    {
        return $this->belongsToMany(Apoderado::class, 'alumno_apoderado')->withPivot('parentesco');
    }

    /** @return HasMany<Permiso, $this> */
    public function permisos(): HasMany
    {
        return $this->hasMany(Permiso::class);
    }

    /** @return HasMany<Matricula, $this> */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    /** Aula del alumno ("2do Secundaria B"), o null si aún no tiene. */
    public function aula(): ?string
    {
        return $this->seccion?->nombreCompleto();
    }
}
