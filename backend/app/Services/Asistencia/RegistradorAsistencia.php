<?php

namespace App\Services\Asistencia;

use App\Enums\EstadoAsistencia;
use App\Enums\EventoAsistencia;
use App\Enums\TipoMensaje;
use App\Exceptions\CodigoNoReconocido;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Personal;
use App\Models\User;
use App\Services\Mensajeria\Mensajero;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Registra la entrada o la salida de quien presenta su carné.
 *
 * Es la única puerta para marcar asistencia: la usan por igual el kiosco web
 * y la app. Un escaneo decide solo de quién es el código y qué toca:
 *
 *   - sin registro hoy            → entrada (puntual o tardanza)
 *   - con entrada, sin salida     → salida, salvo que haya pasado muy poco
 *                                   tiempo desde la entrada (doble escaneo)
 *   - con entrada y salida        → nada, ya terminó su día
 */
class RegistradorAsistencia
{
    public function __construct(
        private readonly Ajustes $ajustes,
        private readonly Mensajero $mensajero,
    ) {}

    /**
     * @throws CodigoNoReconocido
     */
    public function marcar(string $codigo, ?User $registradoPor = null, ?CarbonImmutable $momento = null): ResultadoMarca
    {
        $momento ??= CarbonImmutable::now();
        $persona = $this->buscarPersona(trim($codigo));

        $resultado = $this->registrar($persona, $momento, $registradoPor);

        if ($resultado->esAlumno() && $resultado->evento->registraCambio()) {
            $this->avisarApoderados($resultado, $momento);
        }

        return $resultado;
    }

    private function buscarPersona(string $codigo): Alumno|Personal
    {
        if ($codigo === '') {
            throw CodigoNoReconocido::inexistente();
        }

        $alumno = Alumno::query()->activos()->where('codigo', $codigo)->first();
        $personal = Personal::query()->activos()->where('codigo', $codigo)->first();

        // Un mismo código en las dos tablas: nada permite saber a quién se le
        // está marcando, así que no se adivina.
        if ($alumno && $personal) {
            throw CodigoNoReconocido::repetido($codigo);
        }

        return $alumno ?? $personal ?? throw CodigoNoReconocido::inexistente();
    }

    private function registrar(Alumno|Personal $persona, CarbonImmutable $momento, ?User $registradoPor): ResultadoMarca
    {
        $hoy = $persona->asistencias()->whereDate('fecha', $momento->toDateString())->first();

        if ($hoy === null) {
            return $this->registrarEntrada($persona, $momento, $registradoPor);
        }

        if ($hoy->hora_salida !== null) {
            return new ResultadoMarca(
                EventoAsistencia::SalidaYaRegistrada, $persona, $hoy,
                "{$persona->nombreCompleto()} ya registró su salida hoy."
            );
        }

        if ($this->esDemasiadoProntoParaSalir($hoy, $momento)) {
            return new ResultadoMarca(
                EventoAsistencia::EntradaYaRegistrada, $persona, $hoy,
                "{$persona->nombreCompleto()}, tu entrada ya quedó registrada."
            );
        }

        return $this->registrarSalida($persona, $hoy, $momento);
    }

    private function registrarEntrada(Alumno|Personal $persona, CarbonImmutable $momento, ?User $registradoPor): ResultadoMarca
    {
        $tarde = $this->esTardanza($momento);

        try {
            $asistencia = $persona->asistencias()->create([
                'fecha' => $momento->toDateString(),
                'hora_entrada' => $momento->format('H:i:s'),
                'estado' => $tarde ? EstadoAsistencia::Tarde : EstadoAsistencia::Puntual,
                'registrado_por' => $registradoPor?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Otro escaneo casi simultáneo ya creó la entrada: este no la
            // duplica ni vuelve a avisar.
            $asistencia = $persona->asistencias()->whereDate('fecha', $momento->toDateString())->firstOrFail();

            return new ResultadoMarca(
                EventoAsistencia::EntradaYaRegistrada, $persona, $asistencia,
                "{$persona->nombreCompleto()}, tu entrada ya quedó registrada."
            );
        }

        $hora = $momento->format('H:i');

        return $tarde
            ? new ResultadoMarca(EventoAsistencia::Tardanza, $persona, $asistencia,
                "{$persona->nombreCompleto()} llegó tarde. Entrada: {$hora}")
            : new ResultadoMarca(EventoAsistencia::Entrada, $persona, $asistencia,
                "{$persona->nombreCompleto()}. Entrada: {$hora}");
    }

    private function registrarSalida(Alumno|Personal $persona, Asistencia $hoy, CarbonImmutable $momento): ResultadoMarca
    {
        // El "whereNull" hace que decida la base de datos: de dos escaneos
        // simultáneos solo uno actualiza la fila, y solo ese avisa.
        $marcada = Asistencia::query()
            ->whereKey($hoy->id)
            ->whereNull('hora_salida')
            ->update(['hora_salida' => $momento->format('H:i:s')]);

        $hoy->refresh();

        if ($marcada === 0) {
            return new ResultadoMarca(
                EventoAsistencia::SalidaYaRegistrada, $persona, $hoy,
                "{$persona->nombreCompleto()} ya registró su salida hoy."
            );
        }

        return new ResultadoMarca(
            EventoAsistencia::Salida, $persona, $hoy,
            "Hasta mañana, {$persona->nombreCompleto()}. Salida: {$momento->format('H:i')}"
        );
    }

    /**
     * La hora configurada cuenta entera como puntual: con "07:30", quien pasa
     * a las 07:30:59 llega a tiempo y a las 07:31:00 ya es tardanza.
     */
    private function esTardanza(CarbonImmutable $momento): bool
    {
        $limite = $momento
            ->setTimeFromTimeString($this->ajustes->horaEntrada().':59')
            ->addMinutes($this->ajustes->toleranciaMinutos());

        return $momento->greaterThan($limite);
    }

    private function esDemasiadoProntoParaSalir(Asistencia $hoy, CarbonImmutable $momento): bool
    {
        $entrada = $momento->setTimeFromTimeString($hoy->hora_entrada);

        return $entrada->diffInMinutes($momento) < $this->ajustes->minutosMinimosParaSalida();
    }

    private function avisarApoderados(ResultadoMarca $resultado, CarbonImmutable $momento): void
    {
        /** @var Alumno $alumno */
        $alumno = $resultado->persona;
        $nombre = $alumno->nombreCompleto();
        $hora = $momento->format('H:i');
        $fecha = $momento->format('d/m/Y');

        [$tipo, $titulo, $cuerpo] = match ($resultado->evento) {
            EventoAsistencia::Tardanza => [
                TipoMensaje::Tardanza, 'Llegó tarde al colegio',
                "{$nombre} llegó tarde. Hora de ingreso: {$hora} ({$fecha}).",
            ],
            EventoAsistencia::Salida => [
                TipoMensaje::Salida, 'Salió del colegio',
                "{$nombre} salió del colegio a las {$hora} ({$fecha}).",
            ],
            default => [
                TipoMensaje::Entrada, 'Ingresó al colegio',
                "{$nombre} ingresó al colegio a las {$hora} ({$fecha}).",
            ],
        };

        $this->mensajero->avisarApoderadosDe($alumno, $tipo, $titulo, $cuerpo);
    }
}
