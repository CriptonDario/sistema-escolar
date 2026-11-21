<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un valor de configuración del colegio (clave → valor).
 * Para leerlos y escribirlos se usa App\Support\Ajustes.
 */
class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor'];
}
