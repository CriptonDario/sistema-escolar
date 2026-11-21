<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('nivel', 20)->nullable()->comment('Vacío = se dicta en todos los niveles');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['nombre', 'nivel']);
        });

        // La matrícula de un alumno en un año escolar, en un aula.
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('seccion_id')->constrained('secciones')->restrictOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->string('estado', 20)->default('matriculado');
            $table->date('fecha');
            $table->string('observacion')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['alumno_id', 'anio']);
            $table->index(['anio', 'seccion_id']);
        });

        // Una nota por alumno, materia y periodo. Cuántos periodos tiene el
        // año (bimestres, trimestres o semestres) lo decide la configuración.
        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('materia_id')->constrained('materias')->restrictOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('periodo');
            $table->decimal('nota', 4, 1);
            $table->string('observacion')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['alumno_id', 'materia_id', 'anio', 'periodo'], 'nota_unica');
        });

        Schema::create('comunicados', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 120);
            $table->text('cuerpo');
            $table->string('alcance', 20)->default('todos');
            $table->string('nivel', 20)->nullable();
            $table->foreignId('seccion_id')->nullable()->constrained('secciones')->nullOnDelete();
            $table->unsignedInteger('destinatarios')->default(0);
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Un bloque del horario semanal de un aula.
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('secciones')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia')->comment('1 = lunes ... 6 = sábado');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->foreignId('materia_id')->nullable()->constrained('materias')->nullOnDelete();
            $table->foreignId('personal_id')->nullable()->constrained('personal')->nullOnDelete();
            $table->string('titulo', 60)->nullable()->comment('Bloques sin materia: Recreo, Formación...');
            $table->timestamps();

            $table->index(['seccion_id', 'dia', 'hora_inicio']);
        });

        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('secciones')->cascadeOnDelete();
            $table->foreignId('materia_id')->nullable()->constrained('materias')->nullOnDelete();
            $table->string('tipo', 20)->default('tarea');
            $table->string('titulo', 120);
            $table->text('descripcion')->nullable();
            $table->date('fecha_entrega');
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->timestamps();

            $table->index(['seccion_id', 'fecha_entrega']);
        });

        Schema::create('conductas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('descripcion', 500);
            $table->date('fecha');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->timestamps();

            $table->index(['alumno_id', 'fecha']);
        });
    }

    public function down(): void
    {
        foreach (['conductas', 'tareas', 'horarios', 'comunicados', 'notas', 'matriculas', 'materias'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
