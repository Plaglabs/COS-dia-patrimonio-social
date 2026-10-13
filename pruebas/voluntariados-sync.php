<?php

/*
 * La sincronización con Voluntariados Chile (13/10), sin llamar a su API: las
 * respuestas se fingen con Http::fake. Altas, cambios, bajas, la reaparición,
 * los frenos (lista vacía y caída a menos de la mitad), las sincronizaciones
 * que no están completas y sanas, la paginación, las equivalencias de formato
 * y región, y qué se enseña al público.
 *
 * Dentro de una transacción que se deshace: no deja nada.
 *
 *   php artisan tinker --execute="require base_path('pruebas/voluntariados-sync.php');"
 */

use App\Models\Voluntariado;
use App\Services\SincronizadorVoluntariados;
use App\Support\VoluntariadosChile\Equivalencias;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$ok = 0;
$mal = 0;
$di = function (string $que, bool $bien, ?string $extra = '') use (&$ok, &$mal) {
    $bien ? $ok++ : $mal++;
    echo str_pad($que, 74).($bien ? 'OK' : '*** MAL ***')." {$extra}\n";
};

$op = fn (string $id, array $cambios = []) => array_replace_recursive([
    'id' => $id,
    'status' => 'postulación_abierta',
    'updated_at' => '2026-10-01T12:00:00Z',
    'opportunity_url' => "https://voluntariadoschile.cl/oportunidades?id={$id}",
    'organization' => ['name' => "Org {$id}", 'logo_url' => null, 'type' => 'Organizaciones sin fines de lucro', 'impact_areas' => [['code' => 'AI_SAL', 'name' => 'Salud']]],
    'title' => "Voluntariado {$id}",
    'description' => 'Descripción',
    'responsibilities' => 'Ayudar',
    'external_link' => null,
    'cover_image_url' => null,
    'format' => 'Híbrido',
    'volunteers_needed' => null,
    'location' => ['regions' => ['Metropolitana'], 'comunas' => []],
    'schedule' => ['type' => 'continuous', 'start_date' => null, 'end_date' => null],
    'category' => ['code' => 'CAT_ACE', 'name' => 'Acción comunitaria y emergencias'],
    'requirements' => ['minimum_age' => ['required' => false, 'detail' => null], 'certificate' => ['required' => true, 'detail' => 'De antecedentes']],
    'contact' => ['name' => 'Persona Real', 'email' => 'persona@ejemplo.cl'],
], $cambios);

/*
 * Un solo Http::fake que contesta lo que diga `$responder`. Llamar a
 * Http::fake varias veces no sustituye la respuesta: se suman, y gana la
 * primera que coincide.
 */
$responder = fn ($p) => Http::response([], 500);
Http::fake(function ($p) use (&$responder) {
    return $responder($p);
});
$contestar = function ($cuerpo, int $codigo = 200) use (&$responder) {
    $responder = fn ($p) => Http::response($cuerpo, $codigo);
};

// Una respuesta de una sola página con estas oportunidades.
$una = function (array $ops, ?int $total = null) use ($contestar) {
    $contestar(['generated_at' => now()->toIso8601String(),
        'pagination' => ['page' => 1, 'page_size' => 200, 'total' => $total ?? count($ops)], 'data' => $ops]);
};
$sync = fn () => app(SincronizadorVoluntariados::class)->sincronizar();
$activos = fn () => Voluntariado::where('externo_id', 'like', 'prueba-%')->where('activo', true)->pluck('externo_id')->sort()->values()->all();

DB::beginTransaction();

try {
    Voluntariado::query()->update(['activo' => false]);   // las reales, fuera de la cuenta

    echo "\n=== Sin clave ===\n";
    config(['services.voluntariados_chile.key' => '']);
    $r = $sync();
    $di('sin VCH_API_KEY: fallida, sin tocar nada', $r->resultado === 'fallida' && str_contains($r->mensaje, 'VCH_API_KEY'), $r->mensaje);
    config(['services.voluntariados_chile.key' => 'clave-de-prueba']);

    echo "\n=== Altas, sin cambios y cambios ===\n";
    $una([$op('prueba-a'), $op('prueba-b'), $op('prueba-c')]);
    $r = $sync();
    $di('tres altas', $r->resultado === 'ok' && $r->altas === 3 && $r->bajas === 0, "{$r->resultado} {$r->altas}");
    Http::assertSent(fn ($p) => $p->hasHeader('Authorization', 'Bearer clave-de-prueba') && str_contains($p->url(), 'page_size=200') && ! str_contains($p->url(), 'updated_since'));
    $di('pide con la clave, page_size=200 y sin updated_since', true);
    $r = $sync();
    $di('la misma lista otra vez: ni altas ni cambios', $r->altas === 0 && $r->cambios === 0, "altas {$r->altas} · cambios {$r->cambios}");

    $slug = Voluntariado::where('externo_id', 'prueba-a')->value('slug');
    $una([$op('prueba-a', ['title' => 'Nuevo título']), $op('prueba-b'), $op('prueba-c')]);
    $r = $sync();
    $a = Voluntariado::where('externo_id', 'prueba-a')->first();
    $di('un título cambiado: un cambio', $r->cambios === 1 && $a->titulo === 'Nuevo título', "cambios {$r->cambios}");
    $di('el slug no cambia con el título (los enlaces compartidos siguen)', $a->slug === $slug, $a->slug);
    $di('la respuesta original se guarda entera', ($a->crudo['contact']['email'] ?? null) === 'persona@ejemplo.cl');

    echo "\n=== Bajas y reaparición ===\n";
    $una([$op('prueba-a'), $op('prueba-b')]);
    $r = $sync();
    $di('una deja de llegar: una baja', $r->resultado === 'ok' && $r->bajas === 1 && $activos() === ['prueba-a', 'prueba-b'], json_encode($activos()));
    $c = Voluntariado::where('externo_id', 'prueba-c')->first();
    $di('se apaga, no se borra, con su fecha de baja', $c && ! $c->activo && $c->baja_at !== null);
    $di('fuera de lo público', ! Voluntariado::publicos()->whereKey($c->id)->exists());
    $una([$op('prueba-a'), $op('prueba-b'), $op('prueba-c')]);
    $sync();
    $di('vuelve a llegar: se reactiva, la misma fila', Voluntariado::where('externo_id', 'prueba-c')->count() === 1 && $activos() === ['prueba-a', 'prueba-b', 'prueba-c']);

    echo "\n=== Los frenos ===\n";
    $una([]);
    $r = $sync();
    $di('**lista vacía: frenada, ninguna baja**', $r->resultado === 'frenada' && count($activos()) === 3, "{$r->resultado} · {$r->mensaje}");
    $una([$op('prueba-a')]);
    $r = $sync();
    $di('**1 de 3 (menos de la mitad): frenada, ninguna baja**', $r->resultado === 'frenada' && count($activos()) === 3, "{$r->resultado}");
    $una([$op('prueba-a'), $op('prueba-b')]);
    $r = $sync();
    $di('2 de 3 (la mitad o más): sí da de baja', $r->resultado === 'ok' && $r->bajas === 1);
    $una([$op('prueba-a'), $op('prueba-b'), $op('prueba-c')]);
    $sync();

    echo "\n=== Lo que no está completo y sano no toca nada ===\n";
    $contestar(['error' => ['code' => 'boom', 'message' => 'Fallo']], 500);
    $r = $sync();
    $di('un 500: fallida y las tres siguen', $r->resultado === 'fallida' && count($activos()) === 3, $r->mensaje);
    $una([$op('prueba-a'), $op('prueba-b')], 3);
    $r = $sync();
    $di('el total dice 3 y llegan 2: fallida, sin bajas', $r->resultado === 'fallida' && count($activos()) === 3, $r->mensaje);
    $contestar(['data' => [$op('prueba-a')]]);
    $r = $sync();
    $di('sin pagination.total: fallida', $r->resultado === 'fallida' && count($activos()) === 3, $r->mensaje);
    $contestar(['pagination' => ['total' => 1], 'data' => [['id' => 'prueba-x']]]);
    $r = $sync();
    $di('un elemento sin título: fallida', $r->resultado === 'fallida' && count($activos()) === 3, $r->mensaje);
    $contestar('<html>no</html>');
    $r = $sync();
    $di('una respuesta que no es JSON: fallida', $r->resultado === 'fallida' && count($activos()) === 3, $r->mensaje);

    echo "\n=== Dos páginas ===\n";
    $responder = function ($p) use ($op) {
        parse_str((string) parse_url($p->url(), PHP_URL_QUERY), $q);

        return Http::response(['pagination' => ['page' => (int) $q['page'], 'page_size' => 200, 'total' => 3],
            'data' => (int) $q['page'] === 1 ? [$op('prueba-a'), $op('prueba-b')] : [$op('prueba-c')]]);
    };
    $r = $sync();
    $di('junta las dos páginas y no da de baja nada', $r->resultado === 'ok' && $r->recibidas === 3 && $r->bajas === 0, "{$r->resultado} · {$r->recibidas}");

    echo "\n=== Formato y regiones ===\n";
    foreach (['En persona' => 'Presencial', 'Presencial' => 'Presencial', 'Online' => 'Online', 'En línea' => 'Online', 'Híbrido' => 'Híbrido', 'HIBRIDO' => 'Híbrido', 'Otra cosa' => null] as $suyo => $nuestro) {
        $di("formato «{$suyo}» → ".($nuestro ?? 'sin traducir'), Equivalencias::formato($suyo) === $nuestro, (string) Equivalencias::formato($suyo));
    }
    $id = fn ($nombre) => DB::table('regions')->where('nombre', $nombre)->value('id');
    foreach (['Metropolitana' => 'Metropolitana de Santiago', 'Región Metropolitana de Santiago' => 'Metropolitana de Santiago', "O'Higgins" => "Libertador General Bernardo O'Higgins",
        'Región de Valparaíso' => 'Valparaíso', 'Biobío' => 'Biobío', 'Bio Bio' => 'Biobío', 'Araucanía' => 'La Araucanía', 'Aysén' => 'Aysén del General Carlos Ibáñez del Campo',
        'Magallanes' => 'Magallanes y de la Antártica Chilena', 'Ñuble' => 'Ñuble', 'Los Ríos' => 'Los Ríos'] as $suya => $nuestra) {
        $di("región «{$suya}» → {$nuestra}", Equivalencias::regionId($suya) === $id($nuestra) && $id($nuestra) !== null);
    }
    $di('una región desconocida no se traduce («Los»)', Equivalencias::regionId('Los') === null);

    $una([$op('prueba-a', ['format' => 'En persona', 'location' => ['regions' => ['Metropolitana', 'Narnia'], 'comunas' => ['Providencia']]]), $op('prueba-b'), $op('prueba-c')]);
    $sync();
    $a = Voluntariado::where('externo_id', 'prueba-a')->first();
    $di('guarda el formato original y el traducido', $a->formato_original === 'En persona' && $a->formato === 'Presencial');
    $di('guarda las regiones como llegan y sólo traduce las conocidas', $a->regiones_originales === ['Metropolitana', 'Narnia'] && $a->region_ids === [$id('Metropolitana de Santiago')]);
    $di('la desconocida se enseña tal cual', in_array('Narnia', $a->regiones, true) && in_array('Metropolitana de Santiago', $a->regiones, true), json_encode($a->regiones, JSON_UNESCAPED_UNICODE));
    $di('filtra por la región traducida', Voluntariado::publicos()->porRegion($id('Metropolitana de Santiago'))->whereKey($a->id)->exists());
    $di('con una sola comuna, la tarjeta dice la comuna', $a->lugar_corto === 'Providencia', $a->lugar_corto);
    $di('requisitos: sólo los que exigen, con su detalle', $a->requisitos_exigidos === [['rotulo' => 'Certificado', 'detalle' => 'De antecedentes']]);

    echo "\n=== Qué se enseña ===\n";
    $una([
        $op('prueba-a'),
        $op('prueba-b', ['status' => 'postulación_cerrada']),
        $op('prueba-c', ['schedule' => ['type' => 'fixed_range', 'start_date' => '2020-01-01', 'end_date' => '2020-02-01']]),
        $op('prueba-d', ['schedule' => ['type' => 'fixed_range', 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(20)->toDateString()]]),
        $op('prueba-e', ['cover_image_url' => 'javascript:alert(1)', 'opportunity_url' => 'javascript:alert(1)']),
    ]);
    $sync();
    $publicos = Voluntariado::publicos()->where('externo_id', 'like', 'prueba-%')->pluck('externo_id')->sort()->values()->all();
    $di('fuera la cerrada y la que ya terminó', $publicos === ['prueba-a', 'prueba-d', 'prueba-e'], json_encode($publicos));
    $d = Voluntariado::where('externo_id', 'prueba-d')->first();
    $di('la de rango con fecha: la tarjeta lleva fechas, no «permanente»', $d->fecha_corta !== 'Convocatoria permanente', $d->fecha_corta);
    $e = Voluntariado::where('externo_id', 'prueba-e')->first();
    $di('un enlace que no es http(s) no se guarda', $e->imagen_url === null && $e->enlace_postular === null);
    $di('sin portada: la imagen predeterminada', $e->imagen === App\Models\Activity::imagenPorDefecto());
    $di('por fecha: la de fecha antes que las permanentes', Voluntariado::publicos()->where('externo_id', 'like', 'prueba-%')->porFecha()->first()->externo_id === 'prueba-d');
} finally {
    DB::rollBack();
}

echo "\n{$ok} bien, {$mal} mal\n";
