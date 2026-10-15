<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Command;

/**
 * Carga las tres páginas que se conservan del sitio WordPress anterior (15/10):
 * `/que-es`, `/edicion-2025` y `/primera-edicion`, con la misma URL que tenían
 * allá. El botón «Conoce más» del home apunta a `/que-es`.
 *
 *   php artisan dps:importar-paginas-wordpress --simular
 *   php artisan dps:importar-paginas-wordpress
 *
 * **Es un comando y no una migración** a propósito: es contenido del cliente,
 * como el listado de organizaciones (`dps:importar-organizaciones`), y no todo
 * entorno lo quiere. Se corre una vez donde haga falta —en producción, después
 * del despliegue que lo trae— y después las páginas se editan desde Páginas.
 *
 * Sólo el contenido principal de cada página: ni la cabecera ni el pie de
 * WordPress, ni las franjas de auspiciadores y organizaciones que venían
 * debajo. El HTML está en `database/importacion/paginas/`, reescrito con las
 * piezas de «Páginas a lo ancho» de app.css. Las imágenes están en
 * `public/img/paginas/`, versionadas como el resto del diseño, y
 * `dps:instalar` las indexa en la biblioteca de medios.
 *
 * **Es idempotente y no pisa nada**: una página que ya existe —también en la
 * papelera— no se toca, aunque su contenido sea otro. Si alguien la hizo o la
 * cambió en el panel, manda lo suyo.
 */
class ImportarPaginasWordpress extends Command
{
    protected $signature = 'dps:importar-paginas-wordpress
        {--simular : Dice qué haría, sin escribir nada}';

    protected $description = 'Carga las páginas «Qué es», «Edición 2025» y «Primera edición» del sitio WordPress anterior';

    private const PAGINAS = [
        'que-es' => [
            'titulo' => '¿Qué es el Patrimonio Social?',
            'meta_descripcion' => 'El Patrimonio Social es la capacidad de dar sin esperar nada a cambio. Descubre cómo puedes contribuir.',
        ],
        'edicion-2025' => [
            'titulo' => 'Edición 2025',
            'meta_descripcion' => 'El 5 de diciembre de 2025 celebramos la segunda edición del Día del Patrimonio Social. Estos son sus resultados.',
        ],
        'primera-edicion' => [
            'titulo' => 'Edición 2024',
            'meta_descripcion' => 'El 5 de diciembre de 2024 celebramos por primera vez el Día del Patrimonio Social.',
        ],
    ];

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');
        $creadas = 0;

        foreach (self::PAGINAS as $slug => $datos) {
            $existente = Page::withTrashed()->where('slug', $slug)->first();

            if ($existente) {
                $this->line("  · ya existe, no se toca: /{$slug}".($existente->trashed() ? '  (en la papelera)' : ''));

                continue;
            }

            $archivo = database_path("importacion/paginas/{$slug}.html");

            if (! is_file($archivo)) {
                $this->error("No encuentro el contenido de /{$slug}: {$archivo}");

                return self::FAILURE;
            }

            if (! $simular) {
                Page::create($datos + [
                    'slug' => $slug,
                    'contenido' => file_get_contents($archivo),
                    'activo' => true,
                    'a_lo_ancho' => true,
                ]);
            }

            $this->line("  + /{$slug}  «{$datos['titulo']}»");
            $creadas++;
        }

        $this->info(($simular ? 'Simulación: se crearían' : 'Listo:')." {$creadas} de ".count(self::PAGINAS).' páginas.');

        return self::SUCCESS;
    }
}
