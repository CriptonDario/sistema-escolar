<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Boletas y facturas emitidas a partir de un pago. La numeración (serie +
    // correlativo) es la que exige SUNAT: correlativa y sin huecos por serie.
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->restrictOnDelete();
            $table->string('tipo', 10)->comment('boleta | factura');
            $table->string('serie', 4);
            $table->unsignedInteger('correlativo');
            $table->string('cliente_tipo_doc', 1)->comment('1 = DNI, 6 = RUC, - = sin documento');
            $table->string('cliente_numero_doc', 15)->nullable();
            $table->string('cliente_nombre', 150);
            $table->decimal('total', 9, 2);
            $table->string('estado', 15)->default('pendiente')->comment('pendiente | aceptado | rechazado | error');
            $table->string('mensaje_sunat')->nullable();
            $table->string('enlace_pdf')->nullable();
            $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['serie', 'correlativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
