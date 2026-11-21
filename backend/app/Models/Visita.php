<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visita extends Model
{
    protected $table = 'visitas';

    protected $fillable = ['nombre', 'documento', 'motivo', 'visita_a', 'pase', 'entrada', 'salida', 'registrado_por'];

    protected $attributes = ['salida' => null, 'pase' => null];

    protected function casts(): array
    {
        return ['entrada' => 'datetime', 'salida' => 'datetime'];
    }

    public function sigueAdentro(): bool
    {
        return $this->salida === null;
    }
}
