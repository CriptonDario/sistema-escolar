<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Revisa la configuración antes de publicar el sistema (y de vez en cuando
 * después). Marca lo que dejaría expuesto al colegio.
 */
class RevisarSeguridad extends Command
{
    protected $signature = 'colegio:revisar-seguridad';

    protected $description = 'Revisa que la configuración sea segura para producción';

    public function handle(): int
    {
        $sinActivar = User::query()->where(['debe_cambiar_clave' => true, 'activo' => true])->count();
        $admins = User::query()->where(['rol' => Rol::Admin, 'activo' => true])->count();
        $fcm = (string) config('colegio.push.credenciales_fcm');

        // [qué se revisa, ¿está bien?, ¿es grave si no?, qué hacer]
        $puntos = [
            ['Modo producción (APP_ENV=production)', app()->isProduction(), true, 'Pon APP_ENV=production en el .env.'],
            ['Errores detallados apagados (APP_DEBUG=false)', ! config('app.debug'), true, 'Pon APP_DEBUG=false: con true, un error muestra datos internos a cualquiera.'],
            ['Clave de la aplicación (APP_KEY)', filled(config('app.key')), true, 'Ejecuta php artisan key:generate.'],
            ['Dirección con https (APP_URL)', str_starts_with((string) config('app.url'), 'https://'), true, 'Publica el sistema con certificado y pon APP_URL=https://…'],
            ['Cookie de sesión solo por https (SESSION_SECURE_COOKIE=true)', config('session.secure') === true, true, 'Pon SESSION_SECURE_COOKIE=true en el .env.'],
            ['Sesiones cifradas (SESSION_ENCRYPT=true)', config('session.encrypt') === true, false, 'Recomendado: SESSION_ENCRYPT=true.'],
            ['Contraseña inicial distinta de 123456', config('colegio.clave_inicial') !== '123456', false, 'Cambia COLEGIO_CLAVE_INICIAL por una que no sea obvia y compártela solo con las familias.'],
            ["Cuentas activadas (quedan {$sinActivar} con la contraseña inicial)", $sinActivar === 0, false, 'Pide que entren y creen su contraseña. Se ven en el panel, en Seguridad.'],
            ["Administradores activos: {$admins}", $admins >= 1 && $admins <= 3, false, 'Deja solo los administradores necesarios (Usuarios del sistema).'],
            ['Clave de Firebase fuera de la carpeta pública', $fcm === '' || ! str_starts_with(realpath($fcm) ?: $fcm, public_path()), true, 'Mueve el archivo JSON fuera de public/.'],
        ];

        $graves = 0;
        $this->table(['Punto', 'Estado', 'Qué hacer'], array_map(function (array $punto) use (&$graves) {
            [$nombre, $bien, $grave, $accion] = $punto;
            $graves += (! $bien && $grave) ? 1 : 0;

            return [$nombre, $bien ? 'OK' : ($grave ? 'CORREGIR' : 'Revisar'), $bien ? '' : $accion];
        }, $puntos));

        if ($graves > 0) {
            $this->error("Hay {$graves} punto(s) que corregir antes de publicar.");

            return self::FAILURE;
        }

        $this->info('La configuración es segura para producción.');

        return self::SUCCESS;
    }
}
