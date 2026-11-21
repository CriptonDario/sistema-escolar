<?php

namespace App\Providers;

use App\Models\Alumno;
use App\Models\Personal;
use App\Models\User;
use App\Services\Push\FirebasePush;
use App\Services\Push\PushGateway;
use App\Services\Push\SinPush;
use App\Support\Ajustes;
use App\Support\Marca;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Una sola lectura de la tabla `ajustes` por petición.
        $this->app->scoped(Ajustes::class);
        $this->app->scoped(Marca::class, fn ($app) => Marca::desde($app->make(Ajustes::class)));

        // Con Firebase configurado se envía push; sin él, el sistema funciona
        // igual y los mensajes quedan en la bandeja de la app.
        $this->app->singleton(PushGateway::class, fn () => FirebasePush::desdeArchivo(config('colegio.push.credenciales_fcm')) ?? new SinPush);
    }

    public function boot(): void
    {
        // En las columnas polimórficas se guarda un alias corto y estable, no
        // el nombre de la clase: renombrar una clase no rompe los datos.
        Relation::enforceMorphMap([
            'alumno' => Alumno::class,
            'personal' => Personal::class,
            'user' => User::class,
        ]);

        // Tope de peticiones a la API: por sesión de la app (cada teléfono tiene el
        // suyo) o, sin sesión, por dirección. Frena abusos sin estorbar el uso normal.
        RateLimiter::for('api', fn (Request $request) => $request->bearerToken()
            ? Limit::perMinute(240)->by('token:'.sha1($request->bearerToken()))
            : Limit::perMinute(60)->by('ip:'.$request->ip()));

        // Nombre, logo y color del colegio, disponibles en todas las pantallas.
        View::composer('*', fn ($vista) => $vista->with('marca', $this->app->make(Marca::class)));

        // Fuera de producción, los descuidos de Eloquent (consultas N+1,
        // atributos inexistentes) fallan en vez de pasar en silencio.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
