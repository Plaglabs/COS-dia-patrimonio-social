<?php

namespace App\Services;

use App\Models\Voluntariado;
use App\Models\VoluntariadoSincronizacion;
use App\Support\VoluntariadosChile\Equivalencias;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Trae la lista entera de Voluntariados Chile y deja la tabla `voluntariados`
 * igual que ella.
 *
 * **Siempre la lista completa, sin `updated_since`.** Con el modo incremental
 * sólo llega lo que cambió, y lo que desaparece no cambia: deja de estar. No
 * habría forma de enterarse de una baja. El volumen cabe en una o dos
 * peticiones, así que la completa cuesta lo mismo.
 *
 * Lo delicado es dar de baja, porque una lista que llega incompleta borraría
 * oportunidades válidas. Por eso:
 *
 *  1. Sólo se da de baja tras una sincronización **completa y sana**: todas las
 *     páginas con 200, una lista de objetos con id, y tantos ids distintos
 *     como dice `pagination.total`. Si algo falla, no se toca NADA.
 *  2. **Freno**: si la lista llega vacía, o con menos de la mitad de las que
 *     hay activas, se guardan las que lleguen pero no se da de baja ninguna.
 *     Protege de un fallo suyo que devuelva `data: []` con un 200.
 *  3. Dar de baja es apagar `activo`, no borrar. Si vuelve, se reactiva.
 *
 * Cada pasada deja una fila en `voluntariado_sincronizaciones` y, si no fue
 * bien, una línea en el log. El estado vive en la base: el cron de despliegue
 * vacía la caché cada cinco minutos.
 */
class SincronizadorVoluntariados
{
    /** El máximo que acepta su API por página. */
    public const POR_PAGINA = 200;

    /** Un tope por si la paginación no terminara nunca. */
    private const MAX_PAGINAS = 50;

    public function sincronizar(): VoluntariadoSincronizacion
    {
        $registro = VoluntariadoSincronizacion::create(['empezo_at' => now(), 'resultado' => 'en_curso']);

        try {
            [$items, $aviso] = $this->traerTodo();
        } catch (Throwable $e) {
            return $this->cerrar($registro, 'fallida', ['mensaje' => $e->getMessage()]);
        }

        $activasAntes = Voluntariado::where('activo', true)->count();
        $recibidos = collect($items)->keyBy('id');
        $altas = $cambios = 0;

        try {
            $this->guardar($recibidos, $altas, $cambios);
        } catch (Throwable $e) {
            // La transacción se deshizo entera: la tabla queda como estaba.
            return $this->cerrar($registro, 'fallida', ['recibidas' => $recibidos->count(), 'mensaje' => 'Al guardar: '.$e->getMessage()]);
        }

        // El freno: una lista vacía o que cae a menos de la mitad no da de
        // baja nada. Es más probable un fallo de su lado que media plataforma
        // retirada de golpe.
        $sospechosa = $recibidos->isEmpty() || ($activasAntes > 0 && $recibidos->count() < $activasAntes / 2);

        if ($sospechosa) {
            return $this->cerrar($registro, 'frenada', [
                'recibidas' => $recibidos->count(), 'altas' => $altas, 'cambios' => $cambios,
                'mensaje' => trim("Llegaron {$recibidos->count()} y había {$activasAntes} activas: no se dio de baja ninguna. ".$aviso),
            ]);
        }

        $bajas = Voluntariado::where('activo', true)
            ->whereNotIn('externo_id', $recibidos->keys()->all())
            ->update(['activo' => false, 'baja_at' => now()]);

        return $this->cerrar($registro, 'ok', [
            'recibidas' => $recibidos->count(), 'altas' => $altas, 'cambios' => $cambios, 'bajas' => $bajas,
            'mensaje' => $aviso ?: null,
        ]);
    }

    /** Altas y cambios, todo o nada. */
    private function guardar(\Illuminate\Support\Collection $recibidos, int &$altas, int &$cambios): void
    {
        DB::transaction(function () use ($recibidos, &$altas, &$cambios) {
            foreach ($recibidos as $id => $item) {
                $existente = Voluntariado::where('externo_id', $id)->first();
                $datos = $this->datos($item, $existente);

                if (! $existente) {
                    Voluntariado::create($datos);
                    $altas++;

                    continue;
                }

                // MySQL guarda los objetos JSON con las claves reordenadas, y
                // Laravel los compara respetando el orden: sin esto, todas
                // contarían como cambiadas en cada pasada.
                foreach (['areas_impacto', 'regiones_originales', 'region_ids', 'comunas_originales', 'requisitos', 'crudo'] as $json) {
                    if (self::canonico($existente->{$json}) === self::canonico($datos[$json])) {
                        unset($datos[$json]);
                    }
                }

                $existente->fill($datos);

                if ($existente->isDirty()) {
                    $existente->save();
                    $cambios++;
                }
            }
        });
    }

    /**
     * Todas las páginas, o una excepción si la lista no está completa y sana.
     *
     * @return array{0: array<int, array>, 1: string} los elementos y un aviso no grave
     */
    private function traerTodo(): array
    {
        $clave = (string) config('services.voluntariados_chile.key');

        if ($clave === '') {
            throw new \RuntimeException('Falta VCH_API_KEY en el .env: no se consultó la API.');
        }

        $items = [];
        $total = null;

        for ($pagina = 1; $pagina <= self::MAX_PAGINAS; $pagina++) {
            $respuesta = Http::withToken($clave)->accept('application/json')
                ->timeout(20)->connectTimeout(8)
                ->get(config('services.voluntariados_chile.url'), ['page' => $pagina, 'page_size' => self::POR_PAGINA]);

            if ($respuesta->status() !== 200) {
                throw new \RuntimeException("La página {$pagina} respondió {$respuesta->status()}: ".Str::limit($respuesta->body(), 200));
            }

            $json = $respuesta->json();
            $datos = $json['data'] ?? null;

            if (! is_array($json) || ! is_array($datos) || ! array_is_list($datos)) {
                throw new \RuntimeException("La página {$pagina} no trae una lista en «data».");
            }

            $total ??= is_int($json['pagination']['total'] ?? null) ? $json['pagination']['total'] : null;

            foreach ($datos as $i => $item) {
                if (! is_array($item) || ! is_string($item['id'] ?? null) || trim((string) ($item['title'] ?? '')) === '') {
                    throw new \RuntimeException("El elemento {$i} de la página {$pagina} no trae id o título.");
                }

                $items[] = $item;
            }

            if ($datos === [] || $total === null || count($items) >= $total) {
                break;
            }
        }

        if ($total === null) {
            throw new \RuntimeException('La respuesta no trae pagination.total: no se puede saber si la lista está completa.');
        }

        $distintos = count(array_unique(array_column($items, 'id')));

        if ($distintos !== $total) {
            throw new \RuntimeException("La API dice {$total} y llegaron {$distintos} distintas: la lista no está completa.");
        }

        $aviso = $distintos !== count($items) ? 'Llegaron ids repetidos entre páginas; se tomó una de cada.' : '';

        return [$items, $aviso];
    }

    /** Las columnas de un elemento de la API. */
    private function datos(array $item, ?Voluntariado $existente): array
    {
        $org = is_array($item['organization'] ?? null) ? $item['organization'] : [];
        $lugar = is_array($item['location'] ?? null) ? $item['location'] : [];
        $horario = is_array($item['schedule'] ?? null) ? $item['schedule'] : [];
        $categoria = is_array($item['category'] ?? null) ? $item['category'] : [];

        $regiones = $this->textos($lugar['regions'] ?? []);
        $regionIds = array_values(array_unique(array_filter(array_map([Equivalencias::class, 'regionId'], $regiones))));

        $noReconocidas = array_values(array_filter($regiones, fn ($r) => Equivalencias::regionId($r) === null));
        if ($noReconocidas) {
            Log::info('Voluntariados: región sin equivalencia', ['id' => $item['id'], 'regiones' => $noReconocidas]);
        }

        $areas = collect(is_array($org['impact_areas'] ?? null) ? $org['impact_areas'] : [])
            ->filter(fn ($a) => is_array($a) && filled($a['name'] ?? null))
            ->map(fn ($a) => ['code' => $a['code'] ?? null, 'name' => $a['name']])
            ->values()->all();

        $datos = [
            'externo_id' => $item['id'],
            'titulo' => Str::limit(trim((string) $item['title']), 250, ''),
            'descripcion' => $this->texto($item['description'] ?? null),
            'responsabilidades' => $this->texto($item['responsibilities'] ?? null),
            'estado_api' => $this->texto($item['status'] ?? null),
            'organizacion_nombre' => $this->texto($org['name'] ?? null),
            'organizacion_logo_url' => $this->url($org['logo_url'] ?? null),
            'organizacion_tipo' => $this->texto($org['type'] ?? null),
            'areas_impacto' => $areas,
            'categoria_codigo' => $this->texto($categoria['code'] ?? null),
            'categoria_nombre' => $this->texto($categoria['name'] ?? null),
            'imagen_url' => $this->url($item['cover_image_url'] ?? null),
            'enlace_postular' => $this->url($item['opportunity_url'] ?? null),
            'enlace_externo' => $this->url($item['external_link'] ?? null),
            'formato_original' => $this->texto($item['format'] ?? null),
            'formato' => Equivalencias::formato($item['format'] ?? null),
            'regiones_originales' => $regiones,
            'region_ids' => $regionIds,
            'comunas_originales' => $this->textos($lugar['comunas'] ?? []),
            'horario_tipo' => $this->texto($horario['type'] ?? null),
            'fecha_inicio' => $this->fecha($horario['start_date'] ?? null),
            'fecha_termino' => $this->fecha($horario['end_date'] ?? null),
            'voluntarios_requeridos' => is_int($item['volunteers_needed'] ?? null) && $item['volunteers_needed'] > 0 ? $item['volunteers_needed'] : null,
            'requisitos' => is_array($item['requirements'] ?? null) ? $item['requirements'] : null,
            'crudo' => $item,
            'actualizado_api_at' => $this->fechaHora($item['updated_at'] ?? null),
            'activo' => true,
            'baja_at' => null,
        ];

        // El slug se fija al nacer: un enlace compartido no se rompe porque
        // ellos cambien el título. Lleva un trozo del id para no chocar.
        if (! $existente) {
            $datos['slug'] = Str::limit(Str::slug($datos['titulo']), 80, '').'-'.Str::lower(substr(preg_replace('/[^a-z0-9]/i', '', $item['id']), 0, 8));
        }

        return $datos;
    }

    private function cerrar(VoluntariadoSincronizacion $registro, string $resultado, array $datos): VoluntariadoSincronizacion
    {
        $registro->fill($datos + ['resultado' => $resultado, 'termino_at' => now()])->save();

        if ($resultado !== 'ok') {
            Log::warning("Voluntariados: sincronización {$resultado}", ['mensaje' => $registro->mensaje]);
        }

        return $registro;
    }

    /** Un valor JSON con las claves de los objetos ordenadas, para comparar. */
    private static function canonico(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }

        $valor = array_map([self::class, 'canonico'], $valor);

        if (! array_is_list($valor)) {
            ksort($valor);
        }

        return $valor;
    }

    private function texto(mixed $valor): ?string
    {
        $t = is_scalar($valor) ? trim((string) $valor) : '';

        return $t !== '' ? $t : null;
    }

    /** @return array<int, string> */
    private function textos(mixed $lista): array
    {
        return is_array($lista) ? array_values(array_filter(array_map(fn ($v) => $this->texto($v), $lista))) : [];
    }

    /** Sólo http(s): estos enlaces acaban en un href y en un src. */
    private function url(mixed $valor): ?string
    {
        $t = $this->texto($valor);

        return $t !== null && preg_match('~^https?://~i', $t) && mb_strlen($t) <= 1000 ? $t : null;
    }

    private function fecha(mixed $valor): ?string
    {
        $t = $this->texto($valor);

        try {
            return $t ? Carbon::parse($t)->toDateString() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function fechaHora(mixed $valor): ?Carbon
    {
        $t = $this->texto($valor);

        try {
            return $t ? Carbon::parse($t)->utc() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
