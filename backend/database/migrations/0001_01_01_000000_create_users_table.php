<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una sola tabla de cuentas para todos los perfiles. Los datos propios
        // de cada uno (alumno, personal, apoderado) viven en su tabla y apuntan acá.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            // Con qué se inicia sesión: usuario (admin, guardia), correo
            // (personal, alumno) o teléfono (apoderado).
            $table->string('usuario', 120)->unique();
            $table->string('password');
            $table->string('rol', 20)->index();
            $table->boolean('debe_cambiar_clave')->default(false);
            $table->boolean('activo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
