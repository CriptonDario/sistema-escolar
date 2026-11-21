<?php

namespace App\Actions;

use App\Enums\AlcanceComunicado;
use App\Enums\TipoMensaje;
use App\Models\Apoderado;
use App\Models\Comunicado;
use App\Models\User;
use App\Services\Mensajeria\Mensajero;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Publica un comunicado y lo deja en la bandeja de cada apoderado al que le
 * corresponde. Un apoderado con dos hijos en el alcance lo recibe una vez.
 */
class PublicarComunicado
{
    public function __construct(private readonly Mensajero $mensajero) {}

    /**
     * @param  array<string, mixed>  $datos  titulo, cuerpo, alcance, nivel?, seccion_id?
     */
    public function ejecutar(array $datos, User $publicadoPor): Comunicado
    {
        $alcance = AlcanceComunicado::from($datos['alcance']);

        $comunicado = Comunicado::query()->create([
            'titulo' => $datos['titulo'],
            'cuerpo' => $datos['cuerpo'],
            'alcance' => $alcance,
            'nivel' => $alcance === AlcanceComunicado::Nivel ? $datos['nivel'] : null,
            'seccion_id' => $alcance === AlcanceComunicado::Seccion ? $datos['seccion_id'] : null,
            'publicado_por' => $publicadoPor->id,
        ]);

        $apoderados = $this->destinatarios($comunicado)->get();

        foreach ($apoderados as $apoderado) {
            $this->mensajero->enviar($apoderado, TipoMensaje::Comunicado, $comunicado->titulo, Str::limit($comunicado->cuerpo, 480));
        }

        $comunicado->update(['destinatarios' => $apoderados->count()]);

        return $comunicado;
    }

    /**
     * Apoderados con cuenta activa que tienen algún hijo activo dentro del alcance.
     *
     * @return Builder<Apoderado>
     */
    private function destinatarios(Comunicado $comunicado): Builder
    {
        return Apoderado::query()
            ->whereHas('user', fn (Builder $q) => $q->where('activo', true))
            ->whereHas('hijos', function (Builder $hijos) use ($comunicado) {
                $hijos->where('alumnos.activo', true);

                if ($comunicado->alcance === AlcanceComunicado::Seccion) {
                    $hijos->where('alumnos.seccion_id', $comunicado->seccion_id);
                } elseif ($comunicado->alcance === AlcanceComunicado::Nivel) {
                    $hijos->whereHas('seccion.grado', fn (Builder $g) => $g->where('nivel', $comunicado->nivel->value));
                }
            });
    }
}
