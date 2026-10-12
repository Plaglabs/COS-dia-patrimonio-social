<?php

namespace App\Http\Controllers;

use App\Models\FaqPregunta;
use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /** La página de preguntas frecuentes, la única que se pinta con pestañas. */
    private const PREGUNTAS_FRECUENTES = 'preguntas-frecuentes';

    public function show(Request $request, Page $page)
    {
        abort_unless($page->activo, 404);

        /*
         * Preguntas frecuentes (tanda del 12/10): dos pestañas, «Personas» y
         * «Organizaciones», con las preguntas de la tabla `faq_preguntas`. La
         * pestaña viaja en la URL, así que se puede enlazar a la de
         * organizaciones y funciona sin JavaScript.
         *
         * Si la tabla está vacía —una instalación a la que no llegó la
         * importación— se pinta el HTML de la página, como antes.
         */
        if ($page->slug === self::PREGUNTAS_FRECUENTES && FaqPregunta::where('activo', true)->exists()) {
            /*
             * Sólo las pestañas con alguna pregunta visible: una vacía no se
             * enseña, ni con hueco ni con un aviso de relleno (13/10, al llegar
             * las de organizaciones). Pedir una vacía lleva a la primera que
             * tenga.
             */
            $pestanas = collect(FaqPregunta::PESTANAS)
                ->filter(fn ($nombre, $clave) => FaqPregunta::de($clave)->where('activo', true)->exists())
                ->all();

            $pedida = (string) $request->query('pestana');
            $pestana = array_key_exists($pedida, $pestanas) ? $pedida : array_key_first($pestanas);

            return view('public.preguntas-frecuentes', [
                'page' => $page,
                'pestana' => $pestana,
                'pestanas' => $pestanas,
                'secciones' => FaqPregunta::porSeccion($pestana),
            ]);
        }

        return view('public.page', compact('page'));
    }
}
