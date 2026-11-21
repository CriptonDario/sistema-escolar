<?php

namespace App\Services\Canales;

use App\Support\Integraciones;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envío de mensajes por WhatsApp. El colegio elige el proveedor en
 * Configuración → Integraciones:
 *
 *   ultramsg  De pago, no oficial. Usa un "instance id" y un token.
 *   meta      API oficial de WhatsApp Business. Usa el "Phone Number ID" y un
 *             token permanente. Fuera de la ventana de 24 h, Meta solo deja
 *             enviar plantillas aprobadas; el texto libre funciona si el
 *             apoderado escribió antes.
 */
class WhatsApp
{
    public const PROVEEDORES = ['ultramsg' => 'UltraMsg', 'meta' => 'Meta (WhatsApp Business oficial)'];

    public function __construct(private readonly Integraciones $config) {}

    public function disponible(): bool
    {
        return $this->config->activo('wa_activo')
            && $this->config->texto('wa_instancia') !== ''
            && $this->config->texto('wa_token') !== '';
    }

    /** Devuelve true si el proveedor aceptó el mensaje. Nunca lanza: un aviso fallido no rompe nada. */
    public function enviar(string $telefono, string $mensaje): bool
    {
        $telefono = Integraciones::telefonoInternacional($telefono);

        if (! $this->disponible() || $telefono === '') {
            return false;
        }

        try {
            return match ($this->config->texto('wa_proveedor', 'ultramsg')) {
                'meta' => $this->porMeta($telefono, $mensaje),
                default => $this->porUltraMsg($telefono, $mensaje),
            };
        } catch (Throwable $e) {
            Log::warning('WhatsApp: no se pudo enviar', ['motivo' => $e->getMessage()]);

            return false;
        }
    }

    private function porUltraMsg(string $telefono, string $mensaje): bool
    {
        $respuesta = Http::asForm()->timeout(10)->post(
            'https://api.ultramsg.com/'.$this->config->texto('wa_instancia').'/messages/chat',
            ['token' => $this->config->texto('wa_token'), 'to' => $telefono.'@c.us', 'body' => $mensaje],
        );

        $aceptado = $respuesta->successful() && $respuesta->json('sent') === 'true';

        if (! $aceptado) {
            Log::warning('WhatsApp UltraMsg rechazó el mensaje', ['http' => $respuesta->status()]);
        }

        return $aceptado;
    }

    private function porMeta(string $telefono, string $mensaje): bool
    {
        $respuesta = Http::withToken($this->config->texto('wa_token'))->timeout(10)->post(
            'https://graph.facebook.com/v21.0/'.$this->config->texto('wa_instancia').'/messages',
            ['messaging_product' => 'whatsapp', 'to' => $telefono, 'type' => 'text', 'text' => ['body' => $mensaje]],
        );

        if (! $respuesta->successful()) {
            Log::warning('WhatsApp Meta rechazó el mensaje', ['http' => $respuesta->status(), 'error' => $respuesta->json('error.message')]);
        }

        return $respuesta->successful();
    }
}
