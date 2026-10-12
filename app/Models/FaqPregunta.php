<?php

namespace App\Models;

use App\Services\SanitizadorHtml;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Una pregunta de la página de preguntas frecuentes (tanda del 12/10).
 *
 * La página tiene dos pestañas y cada pregunta pertenece a una; moverla es
 * cambiarle `pestana`. Dentro de la pestaña se agrupan por `seccion`, en el
 * orden en que aparece la primera pregunta de cada una: no hay tabla de
 * secciones, una sección existe mientras alguna pregunta la nombre.
 */
class FaqPregunta extends Model
{
    protected $table = 'faq_preguntas';

    /** Las dos pestañas de la página, en su orden. */
    public const PESTANAS = [
        'personas' => 'Personas',
        'organizaciones' => 'Organizaciones',
    ];

    protected $fillable = ['pestana', 'seccion', 'pregunta', 'respuesta', 'orden', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function scopeDe(Builder $q, string $pestana): Builder
    {
        return $q->where('pestana', $pestana);
    }

    public function scopeOrdenadas(Builder $q): Builder
    {
        return $q->orderBy('orden')->orderBy('id');
    }

    /** La otra pestaña: adónde va al moverla. */
    public function otraPestana(): string
    {
        return $this->pestana === 'personas' ? 'organizaciones' : 'personas';
    }

    /** El siguiente orden de una pestaña: al final de todo. */
    public static function siguienteOrden(string $pestana): int
    {
        return (int) static::de($pestana)->max('orden') + 1;
    }

    /**
     * Las preguntas visibles de una pestaña, agrupadas por sección y en orden.
     *
     * @return Collection<int, array{seccion: ?string, preguntas: Collection<int, self>}>
     */
    public static function porSeccion(string $pestana): Collection
    {
        $grupos = [];

        foreach (static::de($pestana)->where('activo', true)->ordenadas()->get() as $p) {
            $clave = (string) $p->seccion;
            $grupos[$clave] ??= ['seccion' => $p->seccion, 'preguntas' => collect()];
            $grupos[$clave]['preguntas']->push($p);
        }

        return collect(array_values($grupos));
    }

    /**
     * La respuesta lista para pintar: se vuelve a limpiar al pintarla, como
     * todo el HTML que escribe la ONG, y los enlaces a otros sitios se abren
     * en otra pestaña, como en la página de antes. El filtro no deja guardar
     * `target`, así que se pone aquí.
     */
    public function respuestaHtml(): string
    {
        $html = app(SanitizadorHtml::class)->limpiar($this->respuesta);
        $propio = parse_url(config('app.url'), PHP_URL_HOST);

        return preg_replace_callback('/<a href="(https?:\/\/[^"]+)"/i', function ($m) use ($propio) {
            $host = parse_url(html_entity_decode($m[1]), PHP_URL_HOST);

            return $host && $host !== $propio
                ? $m[0].' target="_blank" rel="noopener"'
                : $m[0];
        }, $html);
    }
}
