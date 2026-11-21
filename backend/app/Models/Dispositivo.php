<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un teléfono registrado para recibir notificaciones push de una cuenta.
 */
class Dispositivo extends Model
{
    protected $table = 'dispositivos';

    protected $fillable = ['user_id', 'token', 'plataforma'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
