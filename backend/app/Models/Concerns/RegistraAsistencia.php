<?php

namespace App\Models\Concerns;

use App\Models\Asistencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Lo que comparten alumnos y personal: se identifican con un código de carné
 * y tienen un registro de asistencia por día.
 */
trait RegistraAsistencia
{
    /** @return MorphMany<Asistencia, $this> */
    public function asistencias(): MorphMany
    {
        return $this->morphMany(Asistencia::class, 'persona');
    }

    /** @return MorphOne<Asistencia, $this> */
    public function asistenciaDeHoy(): MorphOne
    {
        return $this->morphOne(Asistencia::class, 'persona')->whereDate('fecha', today());
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('activo'), true);
    }

    /**
     * Busca por código, nombres o apellidos. Cada palabra del texto debe
     * aparecer en alguno de los tres, así "perez ana" encuentra a Ana Pérez.
     */
    public function scopeBuscar(Builder $query, string $texto): Builder
    {
        $palabras = preg_split('/\s+/', trim($texto), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($palabras as $palabra) {
            $patron = '%'.addcslashes($palabra, '\\%_').'%';

            $query->where(function (Builder $q) use ($patron) {
                $q->where($this->qualifyColumn('codigo'), 'like', $patron)
                    ->orWhere($this->qualifyColumn('nombres'), 'like', $patron)
                    ->orWhere($this->qualifyColumn('apellidos'), 'like', $patron);
            });
        }

        return $query;
    }
}
