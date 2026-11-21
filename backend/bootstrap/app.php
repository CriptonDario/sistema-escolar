<?php

use App\Enums\Rol;
use App\Http\Middleware\CabecerasDeSeguridad;
use App\Http\Middleware\ExigirCuentaHabilitada;
use App\Http\Middleware\ExigirFamilia;
use App\Http\Middleware\ExigirPanel;
use App\Http\Middleware\ExigirRol;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cabeceras de seguridad en todas las respuestas, y un tope de peticiones en la API.
        $middleware->append(CabecerasDeSeguridad::class);
        $middleware->throttleApi();

        $middleware->alias([
            'rol' => ExigirRol::class,
            'cuenta.habilitada' => ExigirCuentaHabilitada::class,
            'panel' => ExigirPanel::class,
            'familia' => ExigirFamilia::class,
        ]);

        // La API no redirige a una pantalla de login (sin token responde 401);
        // el panel web sí.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));
        // Quien ya tiene sesión y abre el login va a lo suyo: el apoderado a su portal, el resto al panel.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->rol === Rol::Apoderado
            ? route('familia.inicio')
            : route('panel'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La API siempre responde JSON, también en los errores, aunque el
        // cliente olvide la cabecera Accept.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Inicia sesión para continuar.',
                    'ayuda' => 'Esta dirección es de la API que usa la app móvil. El panel web está en '.url('/'),
                ], 401, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        });
    })->create();
