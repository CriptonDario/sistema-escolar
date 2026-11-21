<?php

namespace App\Actions;

use App\Enums\TipoMensaje;
use App\Models\Alumno;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Mensajeria\Mensajero;

/**
 * Publica una tarea, examen o actividad para un aula y le avisa a los
 * apoderados de sus alumnos.
 */
class PublicarTarea
{
    public function __construct(private readonly Mensajero $mensajero) {}

    /**
     * @param  array<string, mixed>  $datos  seccion_id, materia_id?, tipo, titulo, descripcion?, fecha_entrega
     */
    public function ejecutar(array $datos, User $publicadoPor): Tarea
    {
        $tarea = Tarea::query()->create($datos + ['publicado_por' => $publicadoPor->id]);
        $tarea->load('materia');

        $titulo = ucfirst($tarea->tipo->value).($tarea->materia ? ' de '.$tarea->materia->nombre : '');
        $cuerpo = "{$tarea->titulo}. Fecha: {$tarea->fecha_entrega->format('d/m/Y')}.";

        Alumno::query()->activos()->where('seccion_id', $tarea->seccion_id)->get()
            ->each(fn (Alumno $alumno) => $this->mensajero->avisarApoderadosDe($alumno, TipoMensaje::Tarea, $titulo, "{$alumno->nombres}: {$cuerpo}"));

        return $tarea;
    }
}
