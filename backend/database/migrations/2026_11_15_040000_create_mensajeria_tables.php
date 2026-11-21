<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bandeja de mensajes del apoderado en la app.
        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apoderado_id')->constrained('apoderados')->cascadeOnDelete();
            $table->foreignId('alumno_id')->nullable()->constrained('alumnos')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('titulo', 120);
            $table->string('cuerpo', 500);
            $table->timestamp('leido_en')->nullable();
            $table->timestamps();

            $table->index(['apoderado_id', 'id']);
        });

        // Teléfonos a los que enviar la notificación push de una cuenta.
        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('plataforma', 10)->default('android');
            $table->timestamps();
        });

        // Configuración del colegio que la dirección puede cambiar sin tocar código.
        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
        Schema::dropIfExists('dispositivos');
        Schema::dropIfExists('mensajes');
    }
};
