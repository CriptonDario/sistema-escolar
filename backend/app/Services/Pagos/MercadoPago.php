<?php

namespace App\Services\Pagos;

use App\Support\Integraciones;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cobro en línea con Mercado Pago (Checkout Pro): el apoderado paga en la
 * página de Mercado Pago —tarjeta, Yape u otros— y el sistema nunca ve los
 * datos de la tarjeta.
 *
 * Se configura en Integraciones con el "Access Token" de la cuenta del colegio.
 * Lo que el navegador trae de vuelta no se cree: el pago siempre se consulta
 * a Mercado Pago antes de darlo por bueno.
 */
class MercadoPago
{
    private const API = 'https://api.mercadopago.com';

    public function __construct(private readonly Integraciones $config) {}

    public function disponible(): bool
    {
        return $this->config->activo('mp_activo') && $this->config->texto('mp_token') !== '';
    }

    /**
     * Crea el cobro y devuelve adónde enviar al apoderado para pagar.
     *
     * @return array{id: string, url: string}|null
     */
    public function crearCobro(string $referencia, string $titulo, float $monto): ?array
    {
        if (! $this->disponible()) {
            return null;
        }

        $retorno = route('matricula.retorno');
        $cuerpo = [
            'items' => [['title' => $titulo, 'quantity' => 1, 'unit_price' => round($monto, 2), 'currency_id' => 'PEN']],
            'external_reference' => $referencia,
            'back_urls' => ['success' => $retorno, 'pending' => $retorno, 'failure' => $retorno],
        ];

        // Mercado Pago solo acepta volver solo y avisar a direcciones públicas con https.
        if (str_starts_with($retorno, 'https://')) {
            $cuerpo['auto_return'] = 'approved';
            $cuerpo['notification_url'] = route('api.pagos.mercadopago');
        }

        try {
            $respuesta = Http::withToken($this->config->texto('mp_token'))->timeout(15)
                ->post(self::API.'/checkout/preferences', $cuerpo);
        } catch (Throwable $e) {
            Log::warning('Mercado Pago: no se pudo crear el cobro', ['motivo' => $e->getMessage()]);

            return null;
        }

        if ($respuesta->failed() || blank($respuesta->json('init_point'))) {
            Log::warning('Mercado Pago rechazó el cobro', ['estado' => $respuesta->status(), 'respuesta' => $respuesta->json('message')]);

            return null;
        }

        return ['id' => (string) $respuesta->json('id'), 'url' => (string) $respuesta->json('init_point')];
    }

    /**
     * Pregunta a Mercado Pago por un pago.
     *
     * @return array{aprobado: bool, referencia: string, monto: float}|null
     */
    public function consultarPago(string $id): ?array
    {
        // El id llega desde fuera (navegador o aviso): solo se aceptan números.
        if (! $this->disponible() || ! ctype_digit($id)) {
            return null;
        }

        try {
            $respuesta = Http::withToken($this->config->texto('mp_token'))->timeout(15)->get(self::API.'/v1/payments/'.$id);
        } catch (Throwable $e) {
            Log::warning('Mercado Pago: no se pudo consultar el pago', ['motivo' => $e->getMessage()]);

            return null;
        }

        if ($respuesta->failed()) {
            return null;
        }

        return [
            'aprobado' => $respuesta->json('status') === 'approved',
            'referencia' => (string) $respuesta->json('external_reference'),
            'monto' => (float) $respuesta->json('transaction_amount'),
        ];
    }
}
