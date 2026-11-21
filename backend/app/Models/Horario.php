<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Horario extends Model
{
    protected $table = 'horarios';

    protected $fillable = ['seccion_id', 'dia', 'hora_inicio', 'hora_fin', 'materia_id', 'personal_id', 'titulo'];

    protected function casts(): array
    {
        return ['dia' => 'integer'];
    }

    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];

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

    /** @return BelongsTo<Personal, $this> */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    /** Lo que se dicta en el bloque: la materia o, si no hay, su título. */
    public function nombre(): string
    {
        return $this->materia->nombre ?? $this->titulo ?? 'Sin título';
    }
}
