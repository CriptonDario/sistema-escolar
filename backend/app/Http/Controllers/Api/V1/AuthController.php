<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarClaveRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\PerfilResource;
use App\Models\Apoderado;
use App\Models\Dispositivo;
use App\Models\User;
use App\Support\Ajustes;
use App\Support\FrenoDeLogin;
use App\Support\RegistroDeSeguridad;
use App\Support\Sesiones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    private const INTENTOS_PERMITIDOS = 5;

    private const BLOQUEO_SEGUNDOS = 900;

    /**
     * Un solo login para todos los perfiles. Devuelve el token que la app
     * enviará en cada petición y el perfil con su rol.
     */
    public function login(LoginRequest $request, Ajustes $ajustes): JsonResponse
    {
        $usuario = mb_strtolower(trim($request->validated('usuario')));

        // El freno es por cuenta y no por IP: todo un colegio sale a internet
        // con la misma IP y unos errores de tipeo no deben bloquear al resto.
        $freno = 'login:'.$usuario;

        // Segundo freno, por dirección: contra quien prueba la contraseña inicial en muchas cuentas.
        $frenoIp = FrenoDeLogin::clave($request->ip());

        if (RateLimiter::tooManyAttempts($freno, self::INTENTOS_PERMITIDOS) || RateLimiter::tooManyAttempts($frenoIp, FrenoDeLogin::FALLOS_POR_IP)) {
            $minutos = max(1, (int) ceil(max(RateLimiter::availableIn($freno), RateLimiter::availableIn($frenoIp)) / 60));
            RegistroDeSeguridad::anotar('login_bloqueado', $usuario);

            return response()->json([
                'message' => "Demasiados intentos. Vuelve a intentarlo en {$minutos} minuto(s).",
            ], 429);
        }

        $user = $this->buscarCuenta($usuario);

        if (! $user || ! $user->activo || ! Hash::check($request->validated('clave'), $user->password)) {
            RateLimiter::hit($freno, self::BLOQUEO_SEGUNDOS);
            RateLimiter::hit($frenoIp, FrenoDeLogin::VENTANA_SEGUNDOS);
            RegistroDeSeguridad::anotar('login_fallido', $usuario);

            return response()->json(['message' => 'Usuario o contraseña incorrectos.'], 401);
        }

        RateLimiter::clear($freno);
        RegistroDeSeguridad::anotar('login_app', $user->rol->value, $user);

        $token = $user->createToken(
            $request->validated('dispositivo') ?: 'app',
            ['*'],
            now()->addDays(config('colegio.dias_sesion_app')),
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'perfil' => new PerfilResource($user),
            'colegio' => ['nombre' => $ajustes->nombreColegio()],
        ]);
    }

    public function perfil(Request $request): PerfilResource
    {
        return new PerfilResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        // El teléfono deja de recibir push de la cuenta que cerró sesión.
        if ($request->filled('token_push')) {
            Dispositivo::query()
                ->where('user_id', $request->user()->id)
                ->where('token', $request->string('token_push'))
                ->delete();
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function cambiarClave(CambiarClaveRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('clave_nueva'),
            'debe_cambiar_clave' => false,
        ])->save();

        // Esta sesión sigue; las demás de la cuenta se cierran.
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();
        Sesiones::cerrarWeb($user);
        RegistroDeSeguridad::anotar('clave_cambiada', 'Desde la app', $user);

        return response()->json(['message' => 'Contraseña actualizada.']);
    }

    /**
     * El usuario puede ser un nombre de usuario, un correo o un teléfono
     * escrito de cualquier forma ("+51 987 654 321").
     */
    private function buscarCuenta(string $usuario): ?User
    {
        $user = User::query()->where('usuario', $usuario)->first();

        if ($user === null) {
            $telefono = Apoderado::normalizarTelefono($usuario);

            if ($telefono !== '' && $telefono !== $usuario) {
                $user = User::query()->where('usuario', $telefono)->first();
            }
        }

        return $user;
    }
}
