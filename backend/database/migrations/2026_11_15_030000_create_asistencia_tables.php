<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una fila por persona y por día, sea alumno o personal.
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->morphs('persona'); // persona_type: alumno | personal
            $table->date('fecha');
            $table->time('hora_entrada')->nullable();
            $table->time('hora_salida')->nullable();
            $table->string('estado', 20);
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Dos escaneos casi simultáneos no pueden crear dos entradas: la
            // segunda inserción choca acá y el servicio la trata como repetida.
            $table->unique(['persona_type', 'persona_id', 'fecha'], 'asistencia_unica_por_dia');
            $table->index(['fecha', 'estado']);
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->string('motivo');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->timestamps();

            $table->index(['alumno_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('asistencias');
    }
};
