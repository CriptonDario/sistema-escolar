<?php

namespace Tests;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** Hace las peticiones siguientes como una cuenta del rol indicado. */
    protected function comoRol(Rol $rol): User
    {
        $user = User::factory()->rol($rol)->create();

        Sanctum::actingAs($user);

        return $user;
    }
}
