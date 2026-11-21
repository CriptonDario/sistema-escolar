<?php

namespace Tests\Feature;

use App\Jobs\EnviarPush;
use App\Models\Dispositivo;
use App\Models\User;
use App\Services\Push\FirebasePush;
use App\Services\Push\PushGateway;
use App\Services\Push\SinPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushTest extends TestCase
{
    use RefreshDatabase;

    private function firebase(): FirebasePush
    {
        // Clave generada solo para estas pruebas: no da acceso a nada.
        $pem = file_get_contents(base_path('tests/fixtures/clave_solo_para_pruebas.pem'));

        return new FirebasePush(['project_id' => 'colegio-demo', 'client_email' => 'push@colegio-demo.iam', 'private_key' => $pem]);
    }

    public function test_envia_el_push_a_cada_telefono_de_la_cuenta(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'acceso-1']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);
        $this->app->instance(PushGateway::class, $this->firebase());

        $user = User::factory()->create();
        Dispositivo::query()->create(['user_id' => $user->id, 'token' => 'tok-a', 'plataforma' => 'android']);
        Dispositivo::query()->create(['user_id' => $user->id, 'token' => 'tok-b', 'plataforma' => 'ios']);

        EnviarPush::dispatchSync($user->id, 'Ingresó al colegio', 'Ana ingresó a las 07:20', ['tipo' => 'entrada']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'projects/colegio-demo/messages:send')
            && $request->hasHeader('Authorization', 'Bearer acceso-1')
            && $request['message']['token'] === 'tok-a'
            && $request['message']['notification']['title'] === 'Ingresó al colegio'
            && $request['message']['data']['tipo'] === 'entrada');
        Http::assertSentCount(3); // 1 token de acceso (se reutiliza) + 2 envíos
    }

    public function test_olvida_el_telefono_que_ya_no_tiene_la_app(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'acceso-1']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404),
        ]);
        $this->app->instance(PushGateway::class, $this->firebase());

        $user = User::factory()->create();
        Dispositivo::query()->create(['user_id' => $user->id, 'token' => 'tok-viejo', 'plataforma' => 'android']);

        EnviarPush::dispatchSync($user->id, 'Título', 'Cuerpo');

        $this->assertDatabaseCount('dispositivos', 0);
    }

    public function test_sin_firebase_configurado_no_intenta_enviar_nada(): void
    {
        Http::fake();
        $this->assertInstanceOf(SinPush::class, app(PushGateway::class));

        $user = User::factory()->create();
        Dispositivo::query()->create(['user_id' => $user->id, 'token' => 'tok-a', 'plataforma' => 'android']);

        EnviarPush::dispatchSync($user->id, 'Título', 'Cuerpo');

        Http::assertNothingSent();
        $this->assertDatabaseCount('dispositivos', 1);
    }
}
