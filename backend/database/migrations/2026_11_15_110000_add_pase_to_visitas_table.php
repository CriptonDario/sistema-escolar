<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El pase genérico (VIS-01, VIS-02…) que el guardia le entrega al visitante.
        Schema::table('visitas', function (Blueprint $table) {
            $table->string('pase', 10)->nullable()->after('visita_a')->index();
        });
    }

    public function down(): void
    {
        Schema::table('visitas', function (Blueprint $table) {
            $table->dropIndex(['pase']);
            $table->dropColumn('pase');
        });
    }
};
