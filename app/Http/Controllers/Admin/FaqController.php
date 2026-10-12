<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaqPregunta;
use App\Models\Page;
use App\Services\SanitizadorHtml;
use App\Support\Filtro;
use App\Support\Listado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Páginas → Preguntas frecuentes (tanda del 12/10).
 *
 * Antes era la página entera en un textarea de HTML. Ahora cada pregunta es
 * una fila: se edita con el mismo editor de texto del home, se ordena
 * arrastrando y se pasa de una pestaña a otra con un botón —o cambiando la
 * pestaña en su formulario—. La página (título, URL, descripción, visible)
 * sigue siendo una página suelta del CRUD de páginas.
 */
class FaqController extends Controller
{
    public function __construct(private SanitizadorHtml $limpiar)
    {
    }

    public function index(Request $request)
    {
        $pestana = $this->pestana($request);

        return view('admin.faq.index', [
            'pestana' => $pestana,
            'pestanas' => FaqPregunta::PESTANAS,
            'cuantas' => FaqPregunta::selectRaw('pestana, COUNT(*) n')->groupBy('pestana')->pluck('n', 'pestana'),
            'preguntas' => FaqPregunta::de($pestana)->ordenadas()->get(),
            'pagina' => Page::where('slug', 'preguntas-frecuentes')->first(),
        ]);
    }

    public function create(Request $request)
    {
        $pestana = $this->pestana($request);

        return view('admin.faq.form', [
            'pregunta' => new FaqPregunta(['pestana' => $pestana, 'activo' => true]),
            'pestanas' => FaqPregunta::PESTANAS,
            'secciones' => $this->secciones(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $datos['orden'] = FaqPregunta::siguienteOrden($datos['pestana']);

        FaqPregunta::create($datos);

        return redirect()->route('admin.paginas.preguntas-frecuentes', ['pestana' => $datos['pestana']])
            ->with('ok', 'Pregunta agregada al final de «'.FaqPregunta::PESTANAS[$datos['pestana']].'».');
    }

    public function edit(FaqPregunta $pregunta)
    {
        return view('admin.faq.form', [
            'pregunta' => $pregunta,
            'pestanas' => FaqPregunta::PESTANAS,
            'secciones' => $this->secciones(),
        ]);
    }

    public function update(Request $request, FaqPregunta $pregunta)
    {
        $datos = $this->validar($request);

        // Cambiar de pestaña en el formulario es lo mismo que «Pasar a…»: va
        // al final de la otra, para no colarse entre sus preguntas.
        if ($datos['pestana'] !== $pregunta->pestana) {
            $datos['orden'] = FaqPregunta::siguienteOrden($datos['pestana']);
        }

        $pregunta->update($datos);

        return redirect()->route('admin.paginas.preguntas-frecuentes', ['pestana' => $pregunta->pestana])
            ->with('ok', 'Pregunta guardada.');
    }

    /** «Pasar a Organizaciones / Personas»: a la otra pestaña, al final. */
    public function mover(FaqPregunta $pregunta)
    {
        $origen = $pregunta->pestana;
        $destino = $pregunta->otraPestana();

        $pregunta->update(['pestana' => $destino, 'orden' => FaqPregunta::siguienteOrden($destino)]);

        return redirect()->route('admin.paginas.preguntas-frecuentes', ['pestana' => $origen])
            ->with('ok', '«'.\Illuminate\Support\Str::limit($pregunta->pregunta, 60).'» pasó a «'.FaqPregunta::PESTANAS[$destino].'», al final.');
    }

    public function alternar(FaqPregunta $pregunta)
    {
        $pregunta->update(['activo' => ! $pregunta->activo]);

        return back()->with('ok', $pregunta->activo ? 'La pregunta vuelve a verse en la página.' : 'La pregunta queda oculta: sigue aquí, pero no sale en la página.');
    }

    public function destroy(FaqPregunta $pregunta)
    {
        $pestana = $pregunta->pestana;
        $pregunta->delete();

        return redirect()->route('admin.paginas.preguntas-frecuentes', ['pestana' => $pestana])
            ->with('ok', 'Pregunta eliminada.');
    }

    public function reordenar(Request $request)
    {
        $ids = Listado::ids($request, 'orden');

        if (! $ids) {
            return response()->json(['ok' => false], 422);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $posicion => $id) {
                FaqPregunta::whereKey($id)->update(['orden' => $posicion + 1]);
            }
        });

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'Orden guardado.');
    }

    private function pestana(Request $request): string
    {
        $pedida = Filtro::texto($request, 'pestana');

        return array_key_exists($pedida, FaqPregunta::PESTANAS) ? $pedida : array_key_first(FaqPregunta::PESTANAS);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $request->merge([
            'seccion' => preg_replace('/\s+/u', ' ', trim((string) $request->input('seccion'))),
            'pregunta' => preg_replace('/\s+/u', ' ', trim((string) $request->input('pregunta'))),
            // Se limpia antes de validar: un editor vacío deja `<p><br></p>`,
            // que no es una respuesta.
            'respuesta' => $this->limpiar->limpiar($request->input('respuesta')),
        ]);

        $datos = $request->validate([
            'pestana' => ['required', Rule::in(array_keys(FaqPregunta::PESTANAS))],
            'seccion' => ['nullable', 'string', 'max:255'],
            'pregunta' => ['required', 'string', 'max:500'],
            'respuesta' => ['required', 'string', 'max:20000'],
        ], [
            'respuesta.required' => 'Escribe la respuesta.',
        ], [
            'pestana' => 'la pestaña',
            'seccion' => 'la sección',
            'pregunta' => 'la pregunta',
            'respuesta' => 'la respuesta',
        ]);

        $datos['seccion'] = $datos['seccion'] ?: null;
        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }

    /**
     * Las secciones que ya existen, por pestaña, para sugerirlas al escribir:
     * una sección es un texto, y una tilde distinta abriría otra.
     *
     * @return array<string, array<int, string>>
     */
    private function secciones(): array
    {
        return FaqPregunta::query()->whereNotNull('seccion')->ordenadas()->get(['pestana', 'seccion'])
            ->groupBy('pestana')
            ->map(fn ($g) => $g->pluck('seccion')->unique()->values()->all())
            ->all();
    }
}
