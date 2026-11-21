<?php

namespace App\Actions;

use App\Enums\TipoMensaje;
use App\Enums\TipoPermiso;
use App\Models\Permiso;
use App\Models\User;
use App\Services\Mensajeria\Mensajero;

/**
 * Registra y anula permisos de alumnos. En ambos casos le avisa al apoderado
 * en la app. La usan por igual la API y el panel web.
 */
class GestionarPermisos
{
    public function __construct(private readonly Mensajero $mensajero) {}

    /**
     * @param  array<string, mixed>  $datos  ya validados (GuardarPermisoRequest)
     * @return array{0: Permiso, 1: int} el permiso y a cuántos apoderados se avisó
     */
    public function registrar(array $datos, User $registradoPor): array
    {
        $permiso = Permiso::query()->create($datos + ['registrado_por' => $registradoPor->id]);
        $permiso->load('alumno');

        $avisados = $this->mensajero->avisarApoderadosDe(
            $permiso->alumno,
            TipoMensaje::Permiso,
            'Permiso: '.$permiso->tipo->etiqueta(),
            $this->texto($permiso),
        );

        return [$permiso, $avisados];
    }

    /** Anula el permiso (no se borra). Devuelve false si ya estaba anulado. */
    public function anular(Permiso $permiso): bool
    {
        // El "whereNull" evita avisar dos veces por un doble clic.
        $anulado = Permiso::query()->whereKey($permiso->id)->whereNull('anulado_en')->update(['anulado_en' => now()]);

        if ($anulado === 0) {
            return false;
        }

        $permiso->load('alumno');

        $this->mensajero->avisarApoderadosDe(
            $permiso->alumno,
            TipoMensaje::Permiso,
            'Permiso anulado',
            sprintf(
                'Se anuló el permiso de %s de %s del %s.',
                mb_strtolower($permiso->tipo->etiqueta()),
                $permiso->alumno->nombreCompleto(),
                $permiso->fecha->format('d/m/Y'),
            ),
        );

        return true;
    }

    private function texto(Permiso $permiso): string
    {
        $nombre = $permiso->alumno->nombreCompleto();
        $fecha = $permiso->fecha->format('d/m/Y');
        $hora = substr((string) $permiso->hora, 0, 5);

        $detalle = match ($permiso->tipo) {
            TipoPermiso::SalidaAnticipada => "tiene permiso de salida anticipada el {$fecha} a las {$hora}",
            TipoPermiso::IngresoTarde => "tiene permiso para ingresar tarde el {$fecha}, a las {$hora}",
            TipoPermiso::Inasistencia => "tiene justificada su inasistencia del {$fecha}",
        };

        return "{$nombre} {$detalle}. Motivo: {$permiso->motivo}";
    }
}
