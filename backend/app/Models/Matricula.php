<?php

namespace App\Models;

use App\Enums\EstadoMatricula;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matricula extends Model
{
    protected $table = 'matriculas';

    protected $fillable = ['alumno_id', 'seccion_id', 'anio', 'estado', 'fecha', 'observacion', 'registrado_por'];

    protected function casts(): array
    {
        return ['estado' => EstadoMatricula::class, 'fecha' => 'date:Y-m-d', 'anio' => 'integer'];
    }

    protected $attributes = ['estado' => 'matriculado'];

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** @return BelongsTo<Seccion, $this> */
    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }
}
