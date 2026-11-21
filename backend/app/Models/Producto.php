<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = ['nombre', 'unidad', 'stock_minimo', 'activo'];

    protected $attributes = ['activo' => true, 'stock' => 0, 'stock_minimo' => 0, 'unidad' => 'unidad'];

    protected function casts(): array
    {
        return ['stock' => 'integer', 'stock_minimo' => 'integer', 'activo' => 'boolean'];
    }

    /** @return HasMany<MovimientoInventario, $this> */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function stockBajo(): bool
    {
        return $this->stock <= $this->stock_minimo;
    }
}
