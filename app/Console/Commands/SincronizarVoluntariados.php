<?php

namespace App\Console\Commands;

use App\Services\SincronizadorVoluntariados;
use Illuminate\Console\Command;

/**
 * Trae de Voluntariados Chile la lista completa de oportunidades y deja
 * /voluntariados al día. Lo lanza el planificador cada hora; aquí está por si
 * hay que forzarlo o ver qué pasó. Las reglas, en `SincronizadorVoluntariados`.
 */
class SincronizarVoluntariados extends Command
{
    protected $signature = 'dps:sincronizar-voluntariados';

    protected $description = 'Sincroniza los voluntariados con la API de Voluntariados Chile';

    public function handle(SincronizadorVoluntariados $sincronizador): int
    {
        $r = $sincronizador->sincronizar();

        $this->line("Resultado: {$r->resultado}");
        $this->line("Recibidas {$r->recibidas} · altas {$r->altas} · cambios {$r->cambios} · bajas {$r->bajas}");

        if ($r->mensaje) {
            $this->line($r->mensaje);
        }

        // «Frenada» no es un error del comando: hizo lo que tenía que hacer.
        return $r->resultado === 'fallida' ? self::FAILURE : self::SUCCESS;
    }
}
