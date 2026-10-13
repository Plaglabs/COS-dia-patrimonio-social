@extends('layouts.public')

@section('title', $vol->titulo . ' · ' . config('app.name'))
@section('meta', Str::limit(strip_tags((string) $vol->descripcion), 150))
@section('imagen', $vol->imagen)
{{-- Con la sección apagada, que no la indexe nadie. Ver Voluntariado::seccionVisible(). --}}
@unless (\App\Models\Voluntariado::seccionVisible())
    @section('noindex', '1')
@endunless

@section('content')
{{--
    La ficha de un voluntariado de Voluntariados Chile (13/10), con la
    estructura de la de una actividad: portada, título, quién organiza,
    descripción y bloques a la izquierda; la tarjeta con cuándo, dónde y
    formato a la derecha.

    Lo que cambia es la acción: aquí no se inscribe nadie. El botón lleva a
    postular en su sitio. Y lo que sólo tiene un voluntariado —qué harás y qué
    piden— va como un bloque más, detrás de la descripción, que es donde se
    lee de corrido: de qué va, qué harás, qué te piden.

    No se publica el contacto que trae la API: es el nombre y el correo de una
    persona, y la postulación se hace en su sitio.
--}}
<article style="max-width:1180px;margin:0 auto;padding:40px 40px 88px;">

    <a href="{{ route('voluntariados.index') }}" class="textlink" style="font-size:14px;">← Volver a voluntariados</a>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:44px;margin-top:24px;align-items:start;">
        <div>
            <div style="border-radius:24px;overflow:hidden;aspect-ratio:16/10;background:var(--gris-100);">
                <img loading="lazy" decoding="async" src="{{ $vol->imagen }}" alt="{{ $vol->titulo }}" style="width:100%;height:100%;object-fit:cover;display:block;">
            </div>

            <h1 style="font-weight:800;font-size:36px;line-height:1.12;margin:26px 0 14px;letter-spacing:-.02em;">{{ $vol->titulo }}</h1>

            @if ($vol->organizacion_nombre)
                <div class="org-firma">
                    @if ($vol->organizacion_logo_url)
                        <img loading="lazy" decoding="async" class="org-logo" src="{{ $vol->organizacion_logo_url }}" alt="Logo de {{ $vol->organizacion_nombre }}">
                    @else
                        <span class="org-logo org-logo--iniciales" aria-hidden="true">{{ $vol->iniciales }}</span>
                    @endif

                    <div class="org-datos">
                        <span class="seclabel">Organiza</span>
                        <span class="org-nombre">{{ $vol->organizacion_nombre }}</span>

                        @if ($vol->enlace_externo)
                            {{-- Su «ver más»: un enlace de fuera que no responde de a dónde lleva. --}}
                            <span class="org-enlaces">
                                <a href="{{ $vol->enlace_externo }}" target="_blank" rel="noopener nofollow ugc">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                                    Más información
                                </a>
                            </span>
                        @endif
                    </div>
                </div>
            @endif

            @if ($vol->descripcion)
                <p style="font-size:16px;line-height:1.7;color:var(--gris-700);white-space:pre-line;margin:0 0 24px;">{{ $vol->descripcion }}</p>
            @endif

            @if ($vol->responsabilidades)
                <div style="margin-bottom:18px;" data-ficha-responsabilidades>
                    <div class="seclabel" style="margin-bottom:8px;">Qué harás</div>
                    <p style="margin:0;font-size:15px;line-height:1.6;color:var(--gris-700);white-space:pre-line;">{{ $vol->responsabilidades }}</p>
                </div>
            @endif

            @php $requisitos = $vol->requisitos_exigidos; @endphp
            <div style="margin-bottom:18px;" data-ficha-requisitos>
                <div class="seclabel" style="margin-bottom:8px;">Requisitos</div>
                @if ($requisitos)
                    <ul style="margin:0;padding-left:20px;font-size:15px;line-height:1.6;color:var(--gris-700);">
                        @foreach ($requisitos as $r)
                            <li><strong>{{ $r['rotulo'] }}</strong>@if ($r['detalle']): {{ $r['detalle'] }}@endif</li>
                        @endforeach
                    </ul>
                @else
                    <p style="margin:0;font-size:15px;color:var(--gris-700);">No piden requisitos especiales.</p>
                @endif
            </div>

            @foreach (array_filter(['Categoría' => $vol->categoria_nombre ? [$vol->categoria_nombre] : [], 'Áreas de la organización' => collect($vol->areas_impacto)->pluck('name')->all()]) as $label => $items)
                <div style="margin-bottom:18px;">
                    <div class="seclabel" style="margin-bottom:8px;">{{ $label }}</div>
                    <div style="display:flex;flex-wrap:wrap;gap:7px;">
                        @foreach ($items as $t)
                            <span style="font-size:12.5px;font-weight:600;padding:6px 12px;border-radius:999px;background:var(--gris-100);color:var(--gris-700);">{{ $t }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @include('public.partials.compartir', [
                'enlace' => route('voluntariados.show', $vol),
                'texto' => 'Mira este voluntariado: '.$vol->titulo.' '.route('voluntariados.show', $vol),
                'rotulo' => 'Comparte este voluntariado',
            ])
        </div>

        <aside style="position:sticky;top:100px;">
            <div class="card" style="padding:26px;">
                <div style="display:flex;flex-direction:column;gap:14px;font-size:15px;">
                    <div>
                        <div class="helper" style="font-weight:700;">Cuándo</div>
                        <div style="color:var(--gris-700);">{{ $vol->fecha_larga }}</div>
                    </div>

                    <div>
                        <div class="helper" style="font-weight:700;">Dónde</div>
                        <div style="color:var(--gris-700);">{{ $vol->lugar }}</div>
                    </div>

                    @if ($vol->formato_visible)
                        <div>
                            <div class="helper" style="font-weight:700;">Formato</div>
                            <div style="color:var(--gris-700);">{{ $vol->formato_visible }}</div>
                        </div>
                    @endif

                    @if ($vol->voluntarios_requeridos)
                        <div>
                            <div class="helper" style="font-weight:700;">Voluntarios que buscan</div>
                            <div style="color:var(--gris-700);font-variant-numeric:tabular-nums;">{{ $vol->voluntarios_requeridos }}</div>
                        </div>
                    @endif
                </div>

                @if ($vol->enlace_postular)
                    <a class="btn btn-primary" href="{{ $vol->enlace_postular }}" target="_blank" rel="noopener" data-postular
                       style="margin-top:22px;width:100%;justify-content:center;gap:8px;">
                        Postular en Voluntariados Chile
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    </a>
                    <p class="helper" style="margin:10px 0 0;text-align:center;">Se abre el sitio de Voluntariados Chile, donde se hace la postulación.</p>
                @endif
            </div>
        </aside>
    </div>

    @if ($relacionados->isNotEmpty())
        <section style="margin-top:72px;">
            <h2 style="font-weight:800;font-size:26px;margin:0 0 22px;">Otros voluntariados cerca</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;">
                @foreach ($relacionados as $otro)
                    @include('public.partials.voluntariado-card', ['vol' => $otro])
                @endforeach
            </div>
        </section>
    @endif
</article>
@endsection
