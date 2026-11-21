<?php

namespace App\Models;

use App\Enums\AlcanceComunicado;
use App\Enums\Nivel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comunicado extends Model
{
    protected $table = 'comunicados';

    protected $fillable = ['titulo', 'cuerpo', 'alcance', 'nivel', 'seccion_id', 'destinatarios', 'publicado_por'];

    protected function casts(): array
    {
        return ['alcance' => AlcanceComunicado::class, 'nivel' => Nivel::class, 'destinatarios' => 'integer'];
    }

    /** @return BelongsTo<Seccion, $this> */
    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    /** "Todo el colegio", "Nivel primaria" o "2do Secundaria B". */
    public function destino(): string
    {
        return match ($this->alcance) {
            AlcanceComunicado::Todos => 'Todo el colegio',
            AlcanceComunicado::Nivel => 'Nivel '.($this->nivel->value ?? ''),
            AlcanceComunicado::Seccion => $this->seccion?->nombreCompleto() ?? 'Aula eliminada',
        };
    }
}
