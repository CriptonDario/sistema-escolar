<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una línea del registro de seguridad. No se edita ni se borra desde el sistema. */
class Auditoria extends Model
{
    public const UPDATED_AT = null;

    public const EVENTOS = [
        'login_web' => 'Ingreso por la web',
        'login_app' => 'Ingreso por la app',
        'login_fallido' => 'Intento fallido',
        'login_bloqueado' => 'Ingreso bloqueado por intentos',
        'clave_cambiada' => 'Cambió su contraseña',
        'clave_restablecida' => 'Contraseña restablecida',
        'usuario_creado' => 'Cuenta del panel creada',
        'usuario_editado' => 'Cuenta del panel modificada',
        'usuario_activado' => 'Cuenta reactivada',
        'usuario_desactivado' => 'Cuenta desactivada',
        'integraciones' => 'Integraciones modificadas',
        'matricula_en_linea' => 'Matrícula en línea configurada',
    ];

    protected $table = 'auditoria';

    protected $fillable = ['user_id', 'evento', 'detalle', 'ip'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function etiqueta(): string
    {
        return self::EVENTOS[$this->evento] ?? $this->evento;
    }

    public function esAlerta(): bool
    {
        return in_array($this->evento, ['login_fallido', 'login_bloqueado'], true);
    }
}
