<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una pasada de la sincronización con Voluntariados Chile: cuándo, cómo
 * acabó (`ok`, `frenada`, `fallida`) y qué movió. Ver
 * `App\Services\SincronizadorVoluntariados`.
 */
class VoluntariadoSincronizacion extends Model
{
    protected $table = 'voluntariado_sincronizaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'empezo_at' => 'datetime',
            'termino_at' => 'datetime',
        ];
    }
}
