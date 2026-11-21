<?php

namespace App\Http\Controllers\Web;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Apoderado;
use App\Support\FrenoDeLogin;
use App\Support\RegistroDeSeguridad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Inicio y cierre de sesión de la web.
 *
 * Entran dos grupos, cada uno a lo suyo:
 *   - quienes operan el colegio (administrador, guardia y dirección), al panel;
 *   - los padres y apoderados, a su portal, pero solo si ya activaron la cuenta
 *     desde la app (es decir, ya entraron ahí y crearon su contraseña).
 * Alumnos y docentes usan únicamente la app.
 */
class SesionController extends Controller
{
    private const INTENTOS_PERMITIDOS = 5;

    private const BLOQUEO_SEGUNDOS = 900;

    public function formulario(): View
    {
        return view('sesion.login');
    }

    public function iniciar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string', 'max:120'],
            'clave' => ['required', 'string', 'max:100'],
        ]);

        $usuario = mb_strtolower(trim($datos['usuario']));

        // Mismo freno y misma clave que el login de la API: los intentos
        // fallidos cuentan juntos, entre por donde entre.
        $freno = 'login:'.$usuario;

        $frenoIp = FrenoDeLogin::clave($request->ip());

        if (RateLimiter::tooManyAttempts($freno, self::INTENTOS_PERMITIDOS) || RateLimiter::tooManyAttempts($frenoIp, FrenoDeLogin::FALLOS_POR_IP)) {
            $minutos = max(1, (int) ceil(max(RateLimiter::availableIn($freno), RateLimiter::availableIn($frenoIp)) / 60));
            RegistroDeSeguridad::anotar('login_bloqueado', $usuario);

            return back()->withInput(['usuario' => $datos['usuario']])
                ->withErrors(['usuario' => "Demasiados intentos. Vuelve a intentarlo en {$minutos} minuto(s)."]);
        }

        $valido = Auth::attempt(['usuario' => $usuario, 'password' => $datos['clave'], 'activo' => true]);

        // Un apoderado puede escribir su teléfono de cualquier forma: "+51 987 654 321".
        if (! $valido) {
            $telefono = Apoderado::normalizarTelefono($usuario);
            $valido = $telefono !== '' && $telefono !== $usuario
                && Auth::attempt(['usuario' => $telefono, 'password' => $datos['clave'], 'activo' => true]);
        }

        if (! $valido) {
            RateLimiter::hit($freno, self::BLOQUEO_SEGUNDOS);
            RateLimiter::hit($frenoIp, FrenoDeLogin::VENTANA_SEGUNDOS);
            RegistroDeSeguridad::anotar('login_fallido', $usuario);

            return back()->withInput(['usuario' => $datos['usuario']])
                ->withErrors(['usuario' => 'Usuario o contraseña incorrectos.']);
        }

        RateLimiter::clear($freno);

        $user = $request->user();
        RegistroDeSeguridad::anotar('login_web', $user->rol->value, $user);

        if ($user->rol === Rol::Apoderado) {
            // La cuenta se activa en la app: ahí crea su contraseña. Con la
            // contraseña inicial, que es la misma para todos, no se entra por la web.
            if ($user->debe_cambiar_clave) {
                Auth::logout();

                return back()->withErrors(['usuario' => 'Primero entra desde la app del colegio y crea tu contraseña. Después podrás ingresar aquí.']);
            }

            $request->session()->regenerate();

            return redirect()->route('familia.inicio');
        }

        if (! $user->tieneRol(Rol::Admin, Rol::Guardia, Rol::Direccion)) {
            Auth::logout();

            return back()->withErrors(['usuario' => 'Esta cuenta se usa desde la app móvil.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('panel'));
    }

    public function cerrar(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
