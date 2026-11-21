<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lo que el colegio cobra: matrícula, pensión, materiales...
        Schema::create('conceptos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('tipo', 20)->default('otro');
            $table->decimal('monto', 8, 2)->default(0)->comment('Monto sugerido al registrar el pago');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Un pago recibido. No se borra: si hubo un error, se anula.
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->restrictOnDelete();
            $table->foreignId('concepto_id')->constrained('conceptos_pago')->restrictOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes')->nullable()->comment('Solo en pensiones: el mes que se paga');
            $table->decimal('monto', 8, 2);
            $table->date('fecha');
            $table->string('metodo', 20);
            $table->string('numero_operacion', 50)->nullable();
            $table->string('observacion')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->timestamps();

            $table->index(['alumno_id', 'anio']);
            $table->index('fecha');
        });

        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->string('categoria', 60);
            $table->string('descripcion');
            $table->decimal('monto', 9, 2);
            $table->date('fecha');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->timestamps();

            $table->index('fecha');
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('unidad', 20)->default('unidad');
            $table->integer('stock')->default(0);
            $table->integer('stock_minimo')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Cada entrada o salida de un producto. El stock es la suma de estas filas.
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('tipo', 10);
            $table->unsignedInteger('cantidad');
            $table->string('motivo');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('documento', 20)->nullable();
            $table->string('motivo');
            $table->string('visita_a', 120)->nullable();
            $table->dateTime('entrada');
            $table->dateTime('salida')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('entrada');
        });

        // Bitácora de la puerta: lo que el guardia deja anotado.
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 150);
            $table->text('descripcion');
            $table->string('severidad', 10)->default('baja');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['incidencias', 'visitas', 'movimientos_inventario', 'productos', 'gastos', 'pagos', 'conceptos_pago'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
