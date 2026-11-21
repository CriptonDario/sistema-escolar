<?php

namespace App\Models;

use App\Enums\TipoMensaje;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un mensaje en la bandeja de un apoderado: "Ana ingresó al colegio a las 07:25".
 */
class Mensaje extends Model
{
    protected $table = 'mensajes';

    protected $fillable = ['apoderado_id', 'alumno_id', 'tipo', 'titulo', 'cuerpo', 'leido_en'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMensaje::class,
            'leido_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Apoderado, $this> */
    public function apoderado(): BelongsTo
    {
        return $this->belongsTo(Apoderado::class);
    }

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function scopeNoLeidos(Builder $query): Builder
    {
        return $query->whereNull('leido_en');
    }
}
