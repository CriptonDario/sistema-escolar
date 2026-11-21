<?php

namespace App\Models;

use App\Enums\Severidad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incidencia extends Model
{
    protected $table = 'incidencias';

    protected $fillable = ['titulo', 'descripcion', 'severidad', 'registrado_por'];

    protected function casts(): array
    {
        return ['severidad' => Severidad::class];
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
