<?php

namespace App\Models;

use App\Enums\TipoConcepto;
use Illuminate\Database\Eloquent\Model;

class ConceptoPago extends Model
{
    protected $table = 'conceptos_pago';

    protected $fillable = ['nombre', 'tipo', 'monto', 'activo'];

    protected $attributes = ['activo' => true, 'monto' => 0];

    protected function casts(): array
    {
        return ['tipo' => TipoConcepto::class, 'monto' => 'float', 'activo' => 'boolean'];
    }
}
