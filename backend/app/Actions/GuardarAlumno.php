<?php

namespace App\Actions;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Services\Cuentas\CreadorDeCuentas;
use App\Support\FotoDePersona;
use Illuminate\Support\Facades\DB;

/**
 * Crea o actualiza un alumno y mantiene al día su cuenta de acceso.
 * La usan por igual la API y el panel web.
 */
class GuardarAlumno
{
    private const CAMPOS = ['codigo', 'nombres', 'apellidos', 'dni', 'fecha_nacimiento', 'seccion_id', 'activo'];

    public function __construct(private readonly CreadorDeCuentas $cuentas) {}

    /**
     * @param  array<string, mixed>  $datos  ya validados (GuardarAlumnoRequest)
     */
    public function ejecutar(array $datos, ?Alumno $alumno = null): Alumno
    {
        return DB::transaction(function () use ($datos, $alumno) {
            $alumno ??= new Alumno;
            $alumno->fill(array_intersect_key($datos, array_flip(self::CAMPOS)))->save();

            FotoDePersona::guardar($alumno, $datos, 'alumnos');

            // Con correo el alumno tiene cuenta; si cambia, cambia su usuario.
            if (! empty($datos['correo'])) {
                $this->cuentas->sincronizar($alumno, $datos['correo'], Rol::Alumno);
            }

            // Un alumno dado de baja tampoco entra a la app.
            $alumno->user?->update(['activo' => $alumno->activo]);

            return $alumno;
        });
    }
}
