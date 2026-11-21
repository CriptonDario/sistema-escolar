<?php

namespace App\Models;

use App\Enums\Rol;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cuenta de acceso. Sirve a todos los perfiles; el rol dice cuál es.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = ['nombre', 'usuario', 'password', 'rol', 'debe_cambiar_clave', 'activo'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['activo' => true, 'debe_cambiar_clave' => false, 'remember_token' => null];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'rol' => Rol::class,
            'debe_cambiar_clave' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function tieneRol(Rol ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
    }

    /** @return HasOne<Alumno, $this> */
    public function alumno(): HasOne
    {
        return $this->hasOne(Alumno::class);
    }

    /** @return HasOne<Personal, $this> */
    public function personal(): HasOne
    {
        return $this->hasOne(Personal::class);
    }

    /** @return HasOne<Apoderado, $this> */
    public function apoderado(): HasOne
    {
        return $this->hasOne(Apoderado::class);
    }

    /** @return HasMany<Dispositivo, $this> */
    public function dispositivos(): HasMany
    {
        return $this->hasMany(Dispositivo::class);
    }
}
