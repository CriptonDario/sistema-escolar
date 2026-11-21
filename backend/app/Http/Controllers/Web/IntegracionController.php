<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Canales\WhatsApp;
use App\Support\Integraciones;
use App\Support\RegistroDeSeguridad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Configuración de los servicios externos: WhatsApp, Sofía y SUNAT.
 */
class IntegracionController extends Controller
{
    /** Campos de texto que se guardan tal cual. */
    private const TEXTOS = [
        'wa_proveedor', 'wa_instancia', 'sofia_url',
        'sunat_ruc', 'sunat_razon_social', 'sunat_ruta', 'sunat_serie_boleta', 'sunat_serie_factura', 'sunat_afectacion',
    ];

    private const INTERRUPTORES = ['wa_activo', 'sofia_activo', 'sunat_activo', 'mp_activo'];

    public function editar(Integraciones $config): View
    {
        return view('integraciones.editar', ['config' => $config, 'proveedores' => WhatsApp::PROVEEDORES]);
    }

    public function actualizar(Request $request, Integraciones $config): RedirectResponse
    {
        $datos = $request->validate([
            'wa_proveedor' => ['required', Rule::in(array_keys(WhatsApp::PROVEEDORES))],
            'wa_instancia' => ['nullable', 'string', 'max:100'],
            'wa_token' => ['nullable', 'string', 'max:500'],
            'sofia_url' => ['nullable', 'url:https', 'max:255'],
            'sofia_token' => ['nullable', 'string', 'max:500'],
            'sunat_ruc' => ['nullable', 'digits:11'],
            'sunat_razon_social' => ['nullable', 'string', 'max:150'],
            'sunat_ruta' => ['nullable', 'url:https', 'max:255'],
            'sunat_token' => ['nullable', 'string', 'max:500'],
            'mp_token' => ['nullable', 'string', 'max:500'],
            'sunat_serie_boleta' => ['required', 'regex:/^B[A-Z0-9]{3}$/'],
            'sunat_serie_factura' => ['required', 'regex:/^F[A-Z0-9]{3}$/'],
            'sunat_afectacion' => ['required', Rule::in(['exonerado', 'gravado'])],
        ], [
            'sofia_url.url' => 'La dirección de Sofía debe empezar con https://',
            'sunat_ruta.url' => 'La ruta de Nubefact debe empezar con https://',
            'sunat_ruc.digits' => 'El RUC tiene 11 dígitos.',
            'sunat_serie_boleta.regex' => 'La serie de boletas empieza con B y tiene 4 caracteres (por ejemplo B001).',
            'sunat_serie_factura.regex' => 'La serie de facturas empieza con F y tiene 4 caracteres (por ejemplo F001).',
        ]);

        foreach (self::TEXTOS as $clave) {
            $config->guardar($clave, $datos[$clave] ?? '');
        }
        foreach (self::INTERRUPTORES as $clave) {
            $config->guardar($clave, $request->boolean($clave) ? '1' : '0');
        }
        // Un secreto en blanco significa "no lo cambies": la pantalla nunca
        // muestra el valor guardado, así que vacío no puede querer decir borrar.
        foreach (Integraciones::SECRETOS as $clave) {
            if (! empty($datos[$clave])) {
                $config->guardar($clave, $datos[$clave]);
            }
        }

        RegistroDeSeguridad::anotar('integraciones', 'Activas: '.(implode(', ', array_filter(self::INTERRUPTORES, fn ($clave) => $request->boolean($clave))) ?: 'ninguna'));

        return back()->with('ok', 'Integraciones guardadas.');
    }

    /** Envía un WhatsApp de prueba para comprobar las credenciales. */
    public function probarWhatsApp(Request $request, WhatsApp $whatsapp): RedirectResponse
    {
        $datos = $request->validate(['telefono' => ['required', 'string', 'max:20']]);

        if (! $whatsapp->disponible()) {
            return back()->with('error', 'Activa WhatsApp y guarda la instancia y el token antes de probar.');
        }

        return $whatsapp->enviar($datos['telefono'], 'Mensaje de prueba del sistema del colegio.')
            ? back()->with('ok', 'Mensaje de prueba enviado. Revisa ese WhatsApp.')
            : back()->with('error', 'El proveedor no aceptó el mensaje. Revisa la instancia, el token y el número.');
    }
}
