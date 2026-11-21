<?php

namespace App\Models;

use App\Enums\TipoPersonal;
use App\Models\Concerns\RegistraAsistencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Docentes y demás trabajadores del colegio.
 */
class Personal extends Model
{
    use HasFactory, RegistraAsistencia;

    protected $table = 'personal';

    protected $fillable = ['user_id', 'codigo', 'nombres', 'apellidos', 'dni', 'cargo', 'tipo', 'foto', 'activo'];

    /** Valores con los que nace el registro (los mismos que los de la tabla). */
    protected $attributes = ['activo' => true];

    protected function casts(): array
    {
        return [
            'tipo' => TipoPersonal::class,
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    /** Lo que dice la cabecera de su carné. */
    public function tituloDelCarne(): string
    {
        return $this->tipo === TipoPersonal::Docente ? 'CARNÉ DE DOCENTE' : 'CARNÉ DE PERSONAL';
    }
}
