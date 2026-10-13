{{--
    La tarjeta de un voluntariado de Voluntariados Chile: la misma de las
    actividades (`<x-tarjeta-listado>`). La etiqueta es su categoría tal cual
    llega, sólo informativa: no se mezcla con nuestros temas.
--}}
<x-tarjeta-listado
    :href="route('voluntariados.show', $vol)"
    :imagen="$vol->imagen"
    :titulo="$vol->titulo"
    :etiqueta="$vol->categoria_nombre"
    :lugar="$vol->lugar_corto"
    :fecha="$vol->fecha_corta"
    :descripcion="(string) $vol->descripcion"
    cta="Ver voluntariado" />
