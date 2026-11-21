<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Qué docente dicta qué materia en qué aula, cada año. De acá sale lo que
    // cada docente puede ver y calificar desde la app.
    public function up(): void
    {
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->foreignId('seccion_id')->constrained('secciones')->cascadeOnDelete();
            $table->foreignId('materia_id')->constrained('materias')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->timestamps();

            $table->unique(['seccion_id', 'materia_id', 'anio'], 'una_materia_un_docente_por_aula');
            $table->index(['personal_id', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
