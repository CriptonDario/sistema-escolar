<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Configuración de los servicios externos: WhatsApp, Sofía, SUNAT y pagos en línea.
 *
 * Vive en la tabla `ajustes` como el resto de la configuración, con una
 * diferencia: los tokens y contraseñas se guardan CIFRADOS. Quien lea la base
 * (un respaldo, por ejemplo) no obtiene con eso las credenciales del colegio.
 */
class Integraciones
{
    /** Claves que se guardan cifradas. */
    public const SECRETOS = ['wa_token', 'sofia_token', 'sunat_token', 'mp_token'];

    public function __construct(private readonly Ajustes $ajustes) {}

    public function texto(string $clave, string $porDefecto = ''): string
    {
        $valor = (string) $this->ajustes->obtener($clave, '');

        if ($valor === '') {
            return $porDefecto;
        }
        if (! in_array($clave, self::SECRETOS, true)) {
            return $valor;
        }

        try {
            return Crypt::decryptString($valor);
        } catch (DecryptException) {
            // Se cambió la APP_KEY: el secreto ya no se puede leer y hay que volver a escribirlo.
            return '';
        }
    }

    public function activo(string $clave): bool
    {
        return $this->ajustes->obtener($clave, '0') === '1';
    }

    public function guardar(string $clave, ?string $valor): void
    {
        $valor = trim((string) $valor);

        if ($valor !== '' && in_array($clave, self::SECRETOS, true)) {
            $valor = Crypt::encryptString($valor);
        }

        $this->ajustes->guardar($clave, $valor);
    }

    /** ¿Hay un secreto guardado? Para la pantalla, que nunca lo muestra. */
    public function tieneSecreto(string $clave): bool
    {
        return $this->texto($clave) !== '';
    }

    /** "987654321" → "51987654321": los servicios de mensajería piden el código de país. */
    public static function telefonoInternacional(string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', $telefono);

        return strlen($digitos) === 9 ? '51'.$digitos : $digitos;
    }
}
