<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    public const ENTRADA = 'entrada';

    public const SALIDA = 'salida';

    protected $table = 'movimientos_inventario';

    protected $fillable = ['producto_id', 'tipo', 'cantidad', 'motivo', 'registrado_por'];

    protected function casts(): array
    {
        return ['cantidad' => 'integer'];
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
