<?php

namespace App\Actions;

use App\Enums\Rol;
use App\Models\Personal;
use App\Services\Cuentas\CreadorDeCuentas;
use App\Support\FotoDePersona;
use Illuminate\Support\Facades\DB;

/**
 * Crea o actualiza un trabajador y mantiene al día su cuenta de acceso.
 */
class GuardarPersonal
{
    private const CAMPOS = ['codigo', 'nombres', 'apellidos', 'dni', 'cargo', 'tipo', 'activo'];

    public function __construct(private readonly CreadorDeCuentas $cuentas) {}

    /**
     * @param  array<string, mixed>  $datos  ya validados (GuardarPersonalRequest)
     */
    public function ejecutar(array $datos, ?Personal $personal = null): Personal
    {
        return DB::transaction(function () use ($datos, $personal) {
            $personal ??= new Personal;
            $personal->fill(array_intersect_key($datos, array_flip(self::CAMPOS)))->save();

            FotoDePersona::guardar($personal, $datos, 'personal');

            if (! empty($datos['correo'])) {
                $this->cuentas->sincronizar($personal, $datos['correo'], Rol::Personal);
            }

            $personal->user?->update(['activo' => $personal->activo]);

            return $personal;
        });
    }
}
