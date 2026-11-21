<?php

namespace App\Actions;

use App\Enums\Parentesco;
use App\Enums\Rol;
use App\Models\Apoderado;
use App\Services\Cuentas\CreadorDeCuentas;
use Illuminate\Support\Facades\DB;

/**
 * Crea o actualiza un apoderado. Su usuario en la app es su teléfono: al
 * crearlo nace la cuenta, y si el teléfono cambia, cambia el usuario.
 */
class GuardarApoderado
{
    private const CAMPOS = ['nombres', 'telefono', 'correo', 'dni'];

    public function __construct(private readonly CreadorDeCuentas $cuentas) {}

    /**
     * @param  array<string, mixed>  $datos  ya validados (GuardarApoderadoRequest)
     */
    public function ejecutar(array $datos, ?Apoderado $apoderado = null): Apoderado
    {
        return DB::transaction(function () use ($datos, $apoderado) {
            $campos = array_intersect_key($datos, array_flip(self::CAMPOS));

            if ($apoderado === null) {
                $user = $this->cuentas->crear($datos['nombres'], $datos['telefono'], Rol::Apoderado);
                $apoderado = Apoderado::query()->create($campos + ['user_id' => $user->id]);
            } else {
                $apoderado->update($campos);
                $apoderado->user?->update(['usuario' => $apoderado->telefono, 'nombre' => $apoderado->nombres]);
            }

            foreach ($datos['hijos'] ?? [] as $hijo) {
                $this->vincular($apoderado, (int) $hijo['alumno_id'], $hijo['parentesco'] ?? null);
            }

            return $apoderado;
        });
    }

    public function vincular(Apoderado $apoderado, int $alumnoId, ?string $parentesco = null): void
    {
        $apoderado->hijos()->syncWithoutDetaching([
            $alumnoId => ['parentesco' => $parentesco ?: Parentesco::Apoderado->value],
        ]);
    }
}
