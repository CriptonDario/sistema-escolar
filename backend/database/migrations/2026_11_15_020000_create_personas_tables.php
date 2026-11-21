<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('codigo', 20)->unique()->comment('Lo que lleva el QR del carné');
            $table->string('nombres', 80);
            $table->string('apellidos', 80);
            $table->string('dni', 15)->nullable();
            $table->string('cargo', 80)->nullable();
            $table->string('tipo', 20)->default('docente');
            $table->string('foto')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('seccion_id')->nullable()->constrained('secciones')->restrictOnDelete();
            $table->string('codigo', 20)->unique()->comment('Lo que lleva el QR del carné');
            $table->string('nombres', 80);
            $table->string('apellidos', 80);
            $table->string('dni', 15)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('foto')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('apoderados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nombres', 120);
            $table->string('telefono', 20)->unique()->comment('Solo dígitos; es su usuario en la app');
            $table->string('correo', 120)->nullable();
            $table->string('dni', 15)->nullable();
            $table->timestamps();
        });

        Schema::create('alumno_apoderado', function (Blueprint $table) {
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('apoderado_id')->constrained('apoderados')->cascadeOnDelete();
            $table->string('parentesco', 20)->default('apoderado');

            $table->primary(['alumno_id', 'apoderado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumno_apoderado');
        Schema::dropIfExists('apoderados');
        Schema::dropIfExists('alumnos');
        Schema::dropIfExists('personal');
    }
};
