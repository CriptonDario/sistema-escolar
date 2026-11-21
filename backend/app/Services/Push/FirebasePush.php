<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notificaciones push por Firebase Cloud Messaging (API HTTP v1).
 *
 * Usa la cuenta de servicio que Firebase entrega en
 * Configuración del proyecto → Cuentas de servicio → Generar nueva clave privada.
 */
class FirebasePush implements PushGateway
{
    private const URL_TOKEN = 'https://oauth2.googleapis.com/token';

    private const ALCANCE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @param  array{project_id: string, client_email: string, private_key: string}  $cuenta
     */
    public function __construct(private readonly array $cuenta) {}

    /** Crea el canal desde el archivo JSON, o null si no existe o no es válido. */
    public static function desdeArchivo(?string $ruta): ?self
    {
        if (! $ruta || ! is_readable($ruta)) {
            return null;
        }

        $cuenta = json_decode((string) file_get_contents($ruta), true);

        if (! is_array($cuenta) || empty($cuenta['project_id']) || empty($cuenta['client_email']) || empty($cuenta['private_key'])) {
            Log::warning('FCM_CREDENCIALES no apunta a una cuenta de servicio válida.');

            return null;
        }

        return new self($cuenta);
    }

    public function disponible(): bool
    {
        return true;
    }

    public function enviar(string $tokenDispositivo, string $titulo, string $cuerpo, array $datos = []): ResultadoPush
    {
        $acceso = $this->tokenDeAcceso();

        if ($acceso === null) {
            return ResultadoPush::Error;
        }

        $respuesta = Http::withToken($acceso)
            ->timeout(8)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->cuenta['project_id']}/messages:send", [
                'message' => [
                    'token' => $tokenDispositivo,
                    'notification' => ['title' => $titulo, 'body' => $cuerpo],
                    'data' => array_map('strval', $datos),
                    'android' => ['priority' => 'HIGH'],
                ],
            ]);

        if ($respuesta->successful()) {
            return ResultadoPush::Enviado;
        }

        // 404 UNREGISTERED, o 400 por token mal formado: ese teléfono ya no existe.
        $estado = $respuesta->json('error.status');
        if ($respuesta->status() === 404 || $estado === 'UNREGISTERED' || $estado === 'INVALID_ARGUMENT') {
            return ResultadoPush::TokenInvalido;
        }

        Log::warning('FCM rechazó un push', ['http' => $respuesta->status(), 'estado' => $estado]);

        return ResultadoPush::Error;
    }

    /**
     * Token OAuth de Google para la cuenta de servicio. Vale una hora; se
     * guarda en caché un poco menos para no pedirlo en cada envío.
     */
    private function tokenDeAcceso(): ?string
    {
        return Cache::remember('fcm.token_acceso.'.md5($this->cuenta['client_email']), 3300, function () {
            $respuesta = Http::asForm()->timeout(8)->post(self::URL_TOKEN, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwtFirmado(),
            ]);

            if (! $respuesta->successful() || ! $respuesta->json('access_token')) {
                Log::warning('Google no entregó el token de acceso para FCM', ['http' => $respuesta->status()]);

                return null;
            }

            return $respuesta->json('access_token');
        });
    }

    private function jwtFirmado(): string
    {
        $base64 = fn (string $texto) => rtrim(strtr(base64_encode($texto), '+/', '-_'), '=');
        $ahora = time();

        $cabecera = $base64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $cuerpo = $base64(json_encode([
            'iss' => $this->cuenta['client_email'],
            'scope' => self::ALCANCE,
            'aud' => self::URL_TOKEN,
            'iat' => $ahora,
            'exp' => $ahora + 3600,
        ]));

        openssl_sign("{$cabecera}.{$cuerpo}", $firma, $this->cuenta['private_key'], OPENSSL_ALGO_SHA256);

        return "{$cabecera}.{$cuerpo}.".$base64((string) $firma);
    }
}
