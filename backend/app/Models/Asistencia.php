<?php

namespace App\Models;

use App\Enums\EstadoAsistencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * La asistencia de una persona (alumno o personal) en un día.
 */
class Asistencia extends Model
{
    protected $table = 'asistencias';

    protected $fillable = [
        'persona_type', 'persona_id', 'fecha', 'hora_entrada', 'hora_salida', 'estado', 'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'estado' => EstadoAsistencia::class,
        ];
    }

    /**
     * Un alumno o un trabajador.
     *
     * @return MorphTo<Model, $this>
     */
    public function persona(): MorphTo
    {
        return $this->morphTo();
    }

    /** "07:25:13" → "07:25" */
    public static function horaCorta(?string $hora): ?string
    {
        return $hora === null ? null : substr($hora, 0, 5);
    }
}
