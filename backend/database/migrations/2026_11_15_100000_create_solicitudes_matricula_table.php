<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lo que pide un apoderado cuando la matrícula en línea está abierta.
        // Se vuelve matrícula de verdad cuando el pago queda registrado.
        Schema::create('solicitudes_matricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('apoderado_id')->nullable()->constrained('apoderados')->nullOnDelete();
            $table->foreignId('grado_id')->constrained('grados')->restrictOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->decimal('monto', 8, 2);
            $table->string('estado', 20)->default('pendiente');
            $table->uuid('referencia')->unique()->comment('Con esto la pasarela nos dice qué solicitud se pagó');
            $table->string('pasarela_id', 80)->nullable();
            $table->string('pago_externo_id', 40)->nullable()->unique();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matriculas')->nullOnDelete();
            $table->timestamps();

            $table->unique(['alumno_id', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_matricula');
    }
};
