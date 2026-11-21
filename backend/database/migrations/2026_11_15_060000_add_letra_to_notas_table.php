<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Una nota es un número (escala 0-20) o una letra (AD, A, B, C), según la
    // escala con la que califica el nivel del alumno. Se llena una de las dos.
    public function up(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            $table->decimal('nota', 4, 1)->nullable()->change();
            $table->string('letra', 2)->nullable()->after('nota');
        });
    }

    public function down(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            $table->dropColumn('letra');
        });
    }
};
