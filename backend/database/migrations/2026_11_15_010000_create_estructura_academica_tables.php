<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un grado pertenece a un nivel (inicial, primaria, secundaria).
        Schema::create('grados', function (Blueprint $table) {
            $table->id();
            $table->string('nivel', 20);
            $table->string('nombre', 50);
            $table->unsignedSmallInteger('orden')->default(0)->comment('Posición dentro del nivel');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['nivel', 'nombre']);
        });

        // Cada grado tiene una o más secciones (A, B, C…). La sección es el
        // aula: a ella pertenecen los alumnos.
        Schema::create('secciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grado_id')->constrained('grados')->restrictOnDelete();
            $table->string('nombre', 10);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['grado_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secciones');
        Schema::dropIfExists('grados');
    }
};
