<?php

namespace App\Support\VoluntariadosChile;

use App\Models\Activity;
use App\Models\Region;
use Illuminate\Support\Str;

/**
 * Lo único que se traduce de sus catálogos a los nuestros: el formato y las
 * regiones, porque son los dos filtros de /voluntariados.
 *
 * Todo lo demás —categoría, áreas de impacto, tipo de organización— se guarda
 * y se pinta tal cual llega, sin homologar: si un día cambian su lista, no se
 * rompe nada aquí.
 *
 * La regla con lo que no se reconoce es la misma en los dos: se guarda el
 * valor original, se pinta tal cual, y sólo se queda fuera del filtro. Nada
 * desaparece ni falla por un valor nuevo.
 */
class Equivalencias
{
    /**
     * Formato: lo que llega, ya normalizado (minúsculas, sin tildes), → el
     * nuestro. Su documentación dice «Presencial / Online / Híbrido» y en
     * producción se ha visto «En persona»: se aceptan las dos y las variantes
     * razonables.
     */
    public const FORMATOS = [
        'presencial' => 'Presencial',
        'en persona' => 'Presencial',
        'online' => 'Online',
        'en linea' => 'Online',
        'virtual' => 'Online',
        'remoto' => 'Online',
        'a distancia' => 'Online',
        'hibrido' => 'Híbrido',
        'mixto' => 'Híbrido',
        'semipresencial' => 'Híbrido',
    ];

    /**
     * Regiones: sus nombres —normalizados y sin «Región de/del» delante— →
     * el nombre exacto de la nuestra.
     *
     * Llegan como texto libre y sin código («Metropolitana»), así que la
     * equivalencia se escribe a mano y entera, y NO se adivina por parecido:
     * un «contiene» confundiría «Los Ríos» con «Los Lagos» el día que llegara
     * un «Los» a secas. Además de esta lista vale el nombre completo nuestro
     * escrito igual. Lo que no esté aquí se pinta tal cual y no filtra.
     */
    public const REGIONES = [
        'arica y parinacota' => 'Arica y Parinacota',
        'arica' => 'Arica y Parinacota',
        'tarapaca' => 'Tarapacá',
        'antofagasta' => 'Antofagasta',
        'atacama' => 'Atacama',
        'coquimbo' => 'Coquimbo',
        'valparaiso' => 'Valparaíso',
        'metropolitana' => 'Metropolitana de Santiago',
        'metropolitana de santiago' => 'Metropolitana de Santiago',
        'santiago' => 'Metropolitana de Santiago',
        'rm' => 'Metropolitana de Santiago',
        'o higgins' => "Libertador General Bernardo O'Higgins",
        'ohiggins' => "Libertador General Bernardo O'Higgins",
        'libertador bernardo o higgins' => "Libertador General Bernardo O'Higgins",
        'libertador general bernardo o higgins' => "Libertador General Bernardo O'Higgins",
        'maule' => 'Maule',
        'nuble' => 'Ñuble',
        'biobio' => 'Biobío',
        'bio bio' => 'Biobío',
        'araucania' => 'La Araucanía',
        'la araucania' => 'La Araucanía',
        'los rios' => 'Los Ríos',
        'los lagos' => 'Los Lagos',
        'aysen' => 'Aysén del General Carlos Ibáñez del Campo',
        'aisen' => 'Aysén del General Carlos Ibáñez del Campo',
        'aysen del general carlos ibanez del campo' => 'Aysén del General Carlos Ibáñez del Campo',
        'magallanes' => 'Magallanes y de la Antártica Chilena',
        'magallanes y la antartica chilena' => 'Magallanes y de la Antártica Chilena',
        'magallanes y de la antartica chilena' => 'Magallanes y de la Antártica Chilena',
    ];

    /** @var array<string, int>|null nombre normalizado de la nuestra → id */
    private static ?array $nuestras = null;

    /** El formato nuestro, o null si no se reconoce. */
    public static function formato(?string $suyo): ?string
    {
        $nuestro = self::FORMATOS[self::normal((string) $suyo)] ?? null;

        // Por si alguien toca una lista sin la otra.
        return in_array($nuestro, Activity::FORMATOS, true) ? $nuestro : null;
    }

    /** El id de nuestra región, o null si no se reconoce. */
    public static function regionId(?string $suya): ?int
    {
        $n = self::normal((string) $suya);

        if ($n === '') {
            return null;
        }

        self::$nuestras ??= Region::query()->pluck('id', 'nombre')
            ->mapWithKeys(fn ($id, $nombre) => [self::normal($nombre) => $id])->all();

        $nombre = self::REGIONES[$n] ?? null;

        return self::$nuestras[$nombre !== null ? self::normal($nombre) : $n] ?? null;
    }

    /** Para las pruebas: la lista de regiones se vuelve a leer. */
    public static function olvidar(): void
    {
        self::$nuestras = null;
    }

    private static function normal(string $texto): string
    {
        $t = Str::of($texto)->ascii()->lower()->replaceMatches('/[^a-z0-9 ]+/', ' ')->squish()->value();

        return trim(preg_replace('/^region (de |del )?/', '', $t));
    }
}
