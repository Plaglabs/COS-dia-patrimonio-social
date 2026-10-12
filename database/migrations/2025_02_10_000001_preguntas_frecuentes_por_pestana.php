<?php

use App\Services\SanitizadorHtml;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tanda del 12/10: las preguntas frecuentes pasan a dos pestañas, «Personas» y
 * «Organizaciones», y cada pregunta a su propia fila, para poder editarla y
 * moverla de una pestaña a otra desde el panel. Antes eran un único bloque de
 * HTML en `pages.contenido` (migración `2025_02_09_000001`).
 *
 * **Se importa lo que hay guardado en la página, no una copia del documento**:
 * si la ONG ya corrigió algo desde el panel, eso es lo que pasa. Las 32 del
 * documento del cliente son las de personas (el .docx se titula «Personas»),
 * así que todo va a esa pestaña; la de organizaciones nace vacía.
 *
 * La página sigue existiendo —título, URL, descripción, visible o no— y su
 * `contenido` no se toca: queda como respaldo, y es lo que se pinta si la tabla
 * está vacía.
 *
 * Sólo importa si la tabla está vacía: correrla dos veces no duplica nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('faq_preguntas')) {
            Schema::create('faq_preguntas', function (Blueprint $tabla) {
                $tabla->id();
                $tabla->string('pestana', 30)->default('personas')->index();
                $tabla->string('seccion')->nullable();
                $tabla->string('pregunta', 500);
                $tabla->text('respuesta')->nullable();
                $tabla->unsignedInteger('orden')->default(0);
                $tabla->boolean('activo')->default(true);
                $tabla->timestamps();
            });
        }

        if (DB::table('faq_preguntas')->exists()) {
            return;
        }

        $html = DB::table('pages')->where('slug', 'preguntas-frecuentes')->value('contenido');

        if (blank($html)) {
            return;
        }

        $filas = $this->leer($html);
        $ahora = now();

        foreach ($filas as $i => $fila) {
            DB::table('faq_preguntas')->insert($fila + [
                'pestana' => 'personas',
                'orden' => $i + 1,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_preguntas');
    }

    /**
     * Las preguntas del HTML de la página: cada `<h2>` abre una sección y cada
     * `<details>` es una pregunta (`<summary>`) con su respuesta (lo demás).
     *
     * @return array<int, array{seccion: ?string, pregunta: string, respuesta: string}>
     */
    private function leer(string $html): array
    {
        $dom = new DOMDocument;
        // El prefijo le dice a DOMDocument que es UTF-8; sin él, las tildes
        // salen como Latin-1 roto.
        @$dom->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $limpiar = app(SanitizadorHtml::class);
        $seccion = null;
        $filas = [];

        foreach ((new DOMXPath($dom))->query('//h2 | //details') as $nodo) {
            if ($nodo->nodeName === 'h2') {
                $seccion = trim($nodo->textContent) ?: null;

                continue;
            }

            $resumen = $nodo->getElementsByTagName('summary')->item(0);
            $pregunta = trim(preg_replace('/\s+/u', ' ', $resumen?->textContent ?? ''));

            if ($pregunta === '') {
                continue;
            }

            $respuesta = '';
            foreach (iterator_to_array($nodo->childNodes) as $hijo) {
                if ($hijo !== $resumen) {
                    $respuesta .= $dom->saveHTML($hijo);
                }
            }

            $filas[] = [
                'seccion' => $seccion ? mb_substr($seccion, 0, 255) : null,
                'pregunta' => mb_substr($pregunta, 0, 500),
                // El mismo filtro que aplicará el panel al guardar: así lo
                // importado y lo editado después tienen la misma forma.
                'respuesta' => $limpiar->limpiar($respuesta),
            ];
        }

        return $filas;
    }
};
