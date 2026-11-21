<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Padre, madre o apoderado. Entra a la app con su teléfono y ve a sus hijos.
 */
class Apoderado extends Model
{
    use HasFactory;

    protected $table = 'apoderados';

    protected $fillable = ['user_id', 'nombres', 'telefono', 'correo', 'dni'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Alumno, $this> */
    public function hijos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'alumno_apoderado')->withPivot('parentesco');
    }

    /** @return HasMany<Mensaje, $this> */
    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class);
    }

    /**
     * Teléfonos en los que tiene la app instalada y recibe push.
     *
     * @return HasManyThrough<Dispositivo, User, $this>
     */
    public function dispositivos(): HasManyThrough
    {
        return $this->hasManyThrough(Dispositivo::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    /** Busca por nombre o por teléfono. */
    public function scopeBuscar(Builder $query, string $texto): Builder
    {
        $patron = '%'.addcslashes(trim($texto), '\\%_').'%';

        return $query->where(fn (Builder $q) => $q->where('nombres', 'like', $patron)->orWhere('telefono', 'like', $patron));
    }

    public function esResponsableDe(int $alumnoId): bool
    {
        return $this->hijos()->whereKey($alumnoId)->exists();
    }

    /** "+51 987-654-321" → "987654321". Quita el prefijo 51 de Perú si viene. */
    public static function normalizarTelefono(string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', $telefono);

        if (strlen($digitos) === 11 && str_starts_with($digitos, '51')) {
            $digitos = substr($digitos, 2);
        }

        return $digitos;
    }
}
