<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras que le dicen al navegador cómo proteger las páginas del sistema:
 * que no se puedan incrustar en otro sitio (clickjacking), que no cargue
 * programas ni envíe formularios a sitios ajenos, y que use siempre https.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);
        $cabeceras = $respuesta->headers;

        $cabeceras->set('X-Content-Type-Options', 'nosniff');
        $cabeceras->set('X-Frame-Options', 'DENY');
        $cabeceras->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // La cámara solo la usa el propio sistema (el kiosco lee los carnés).
        $cabeceras->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure()) {
            $cabeceras->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (str_contains((string) $cabeceras->get('Content-Type'), 'text/html')) {
            $cabeceras->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                // Las pantallas usan pequeños scripts y estilos escritos en la propia página.
                "script-src 'self' 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' data: https://fonts.gstatic.com",
                "img-src 'self' data: blob:",
                "media-src 'self' blob:",
                "worker-src 'self' blob:",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "frame-ancestors 'none'",
                // Los formularios solo envían al propio sistema; el pago en línea redirige a Mercado Pago.
                "form-action 'self' https://*.mercadopago.com https://*.mercadopago.com.pe",
            ]));
        }

        return $respuesta;
    }
}
