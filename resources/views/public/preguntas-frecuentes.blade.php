@extends('layouts.public')
@section('title', $page->titulo . ' · ' . config('app.name'))
@section('meta', $page->meta_descripcion ?? '')

{{--
    Preguntas frecuentes con dos pestañas (tanda del 12/10). Las pestañas son
    enlaces: la elegida viaja en la URL (`?pestana=organizaciones`), así que se
    puede compartir y no depende de JavaScript.

    El marcado de las preguntas es el de siempre —`.faq`, un `<h2>` por sección
    y un `<details>` por pregunta—, con sus estilos de `.pagina-texto .faq`.

    Sólo llegan las pestañas con preguntas visibles (`PageController`). Con una
    sola no se pinta la barra: una pestaña sola no elige nada.
--}}

@section('content')
<article style="max-width:760px;margin:0 auto;padding:56px 40px 88px;" class="faq-pagina">
    <h1 style="font-weight:800;font-size:38px;line-height:1.12;margin:0 0 24px;letter-spacing:-.02em;">{{ $page->titulo }}</h1>

    @if (count($pestanas) > 1)
        <nav class="faq-pestanas" aria-label="Preguntas frecuentes para">
            @foreach ($pestanas as $clave => $nombre)
                <a href="{{ route('pages.show', ['page' => $page->slug] + ($clave === array_key_first($pestanas) ? [] : ['pestana' => $clave])) }}"
                   class="faq-pestana {{ $clave === $pestana ? 'activa' : '' }}"
                   @if ($clave === $pestana) aria-current="page" @endif
                   data-faq-pestana="{{ $clave }}">{{ $nombre }}</a>
            @endforeach
        </nav>
    @endif

    <div class="pagina-texto" style="font-size:16px;line-height:1.75;color:var(--gris-700);">
        <div class="faq" data-faq="{{ $pestana }}">
            @foreach ($secciones as $grupo)
                @if ($grupo['seccion'])
                    <h2>{{ $grupo['seccion'] }}</h2>
                @endif
                @foreach ($grupo['preguntas'] as $p)
                    <details>
                        <summary>{{ $p->pregunta }}</summary>
                        <div>{!! $p->respuestaHtml() !!}</div>
                    </details>
                @endforeach
            @endforeach
        </div>
    </div>
</article>
@endsection
