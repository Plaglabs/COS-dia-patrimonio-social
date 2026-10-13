@extends('layouts.public')

@section('title', 'Voluntariados · ' . config('app.name'))
@section('meta', 'Oportunidades de voluntariado de Voluntariados Chile para el Día del Patrimonio Social.')
{{-- Con la sección apagada, que no la indexe nadie. Ver Voluntariado::seccionVisible(). --}}
@unless (\App\Models\Voluntariado::seccionVisible())
    @section('noindex', '1')
@endunless

@section('content')
{{--
    Las oportunidades de Voluntariados Chile (13/10), con la misma cabecera,
    filtros, tarjetas y paginación que el listado de actividades. Sin
    calendario: casi todas son de convocatoria permanente y no tienen día.

    Los filtros se pintan sólo cuando hay algo que filtrar —ver
    `VoluntariadoController`—, y cada desplegable sólo con dos opciones o más.
--}}
<section style="max-width:1180px;margin:0 auto;padding:56px 40px 88px;">
    <div style="margin-bottom:34px;">
        <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:12px;">Voluntariados Chile</div>
        <h1 style="font-weight:800;font-size:40px;line-height:1.1;margin:0 0 12px;letter-spacing:-.02em;">Oportunidades de voluntariado</h1>
        <p style="font-size:16px;color:var(--gris);margin:0;">
            @if ($hayFiltro)
                {{ $voluntariados->total() }} de {{ $total }} {{ \App\Support\Texto::plural('voluntariado', $total) }} con estos filtros.
            @else
                {{ $total }} {{ \App\Support\Texto::plural('voluntariado', $total) }} {{ \App\Support\Texto::plural('disponible', $total) }}. Se postula en el sitio de Voluntariados Chile.
            @endif
        </p>
    </div>

    @if ($conFiltros)
        <form method="GET" class="card" data-filtros-voluntariados style="padding:20px 22px;margin-bottom:22px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;align-items:end;">
            <div style="grid-column:1 / -1;">
                <label class="helper" for="f-q" style="display:block;margin-bottom:6px;font-weight:600;">Buscar</label>
                <div style="position:relative;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                         style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--gris);pointer-events:none;"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input class="fld" type="search" name="q" id="f-q" value="{{ request('q') }}" maxlength="100"
                           placeholder="Nombre del voluntariado u organización" style="padding-left:42px;">
                </div>
            </div>

            @if ($regiones->count() >= 2 || request('region'))
                <div>
                    <label class="helper" for="f-region" style="display:block;margin-bottom:6px;font-weight:600;">Región</label>
                    <select class="fld" name="region" id="f-region" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        @foreach ($regiones as $r)
                            <option value="{{ $r->id }}" @selected(request('region') == $r->id)>{{ $r->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($formatos->count() >= 2 || request('formato'))
                <div>
                    <label class="helper" for="f-formato" style="display:block;margin-bottom:6px;font-weight:600;">Formato</label>
                    <select class="fld" name="formato" id="f-formato" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        @foreach ($formatos as $f)
                            <option value="{{ $f }}" @selected(request('formato') === $f)>{{ $f }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                <a href="{{ route('voluntariados.index') }}" class="btn btn-ghost btn-sm">Limpiar</a>
            </div>
        </form>
    @endif

    @if ($voluntariados->isEmpty())
        <div class="card" style="padding:44px;text-align:center;">
            <p style="font-size:16px;color:var(--gris);margin:0 0 18px;">
                {{ $hayFiltro ? 'No encontramos voluntariados con esos filtros.' : 'Todavía no hay voluntariados publicados. Vuelve pronto.' }}
            </p>
            @if ($hayFiltro)
                <a href="{{ route('voluntariados.index') }}" class="btn btn-outline">Ver todos</a>
            @endif
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:26px;">
            @foreach ($voluntariados as $vol)
                @include('public.partials.voluntariado-card', ['vol' => $vol])
            @endforeach
        </div>

        <div style="margin-top:40px;">{{ $voluntariados->links() }}</div>
    @endif

    {{-- El camino a todo su catálogo, que es más que lo que comparten con el DPS. --}}
    <p style="margin:40px 0 0;font-size:15px;">
        <a class="textlink" href="https://voluntariadoschile.cl/oportunidades" target="_blank" rel="noopener" data-todos-vch>
            Ver todos los voluntariados en Voluntariados Chile →
        </a>
    </p>
</section>
@endsection
