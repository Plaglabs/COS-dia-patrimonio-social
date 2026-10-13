<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\Voluntariado;
use App\Support\Filtro;
use Illuminate\Http\Request;

/**
 * /voluntariados: las oportunidades de Voluntariados Chile (13/10).
 *
 * Lee de la tabla `voluntariados`, que rellena la sincronización cada hora;
 * una visita nunca llama a su API. Son contenido de ellos y no se mezclan con
 * las actividades del DPS: ni en las consultas ni en los catálogos.
 *
 * Tres filtros —búsqueda, región y formato— y no el de tema: su categoría es
 * su catálogo, se enseña tal cual en la tarjeta y no se traduce a nuestros
 * temas, para que un cambio en su lista no nos rompa nada.
 */
class VoluntariadoController extends Controller
{
    /** Con menos de esto no hay nada que filtrar y la barra sólo estorba. */
    private const MINIMO_PARA_FILTRAR = 2;

    public function index(Request $request)
    {
        $region = $request->integer('region') ?: null;
        $formato = Filtro::texto($request, 'formato') ?: null;
        $busqueda = mb_substr(Filtro::texto($request, 'q'), 0, 100);

        $publicos = Voluntariado::publicos();

        /*
         * Las opciones de cada filtro salen de lo que hay, no de nuestro
         * catálogo entero: dieciséis regiones para un voluntariado sería
         * ofrecer quince caminos a «no encontramos nada». Y un desplegable con
         * una sola opción no filtra nada, así que no se pinta.
         */
        $todos = (clone $publicos)->get(['region_ids', 'formato']);
        $regiones = Region::query()->whereIn('id', $todos->pluck('region_ids')->flatten()->unique()->filter())->ordered()->get();
        $formatos = $todos->pluck('formato')->filter()->unique()->sort()->values();

        $voluntariados = (clone $publicos)
            ->porRegion($region)
            ->porFormato($formato)
            ->porTexto($busqueda)
            ->porFecha()
            ->paginate(12)
            ->withQueryString();

        $hayFiltro = $region || $formato || $busqueda !== '';

        return view('public.voluntariados.index', [
            'voluntariados' => $voluntariados,
            'total' => $todos->count(),
            'regiones' => $regiones,
            'formatos' => $formatos,
            // Con un filtro puesto, la barra se queda aunque haya poco: si no,
            // no habría forma de quitarlo.
            'conFiltros' => $todos->count() >= self::MINIMO_PARA_FILTRAR || $hayFiltro,
            'hayFiltro' => $hayFiltro,
        ]);
    }

    public function show(Voluntariado $voluntariado)
    {
        // Una que ya no llega o que cerró da 404, como una actividad sin
        // publicar: la dirección deja de existir para el público.
        abort_unless(Voluntariado::publicos()->whereKey($voluntariado->id)->exists(), 404);

        $relacionados = Voluntariado::publicos()
            ->whereKeyNot($voluntariado->id)
            ->when($voluntariado->region_ids, fn ($q, $ids) => $q->porRegion($ids[0]))
            ->porFecha()->take(3)->get();

        return view('public.voluntariados.show', [
            'vol' => $voluntariado,
            'relacionados' => $relacionados,
        ]);
    }
}
