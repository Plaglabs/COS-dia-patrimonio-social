@extends('layouts.admin')
@section('title', 'Preguntas frecuentes')

{{--
    Las preguntas frecuentes, por pestaña (tanda del 12/10). Cada pregunta se
    edita en su propia pantalla, se ordena arrastrando y se pasa a la otra
    pestaña con un botón. El orden de las secciones en la página es el de su
    primera pregunta.
--}}

@section('actions')
    @if ($pagina)
        <a class="btn btn-outline btn-sm" href="{{ route('pages.show', ['page' => $pagina->slug] + ($pestana === array_key_first($pestanas) ? [] : ['pestana' => $pestana])) }}" target="_blank" rel="noopener">Ver la página</a>
    @endif
    <a class="btn btn-primary btn-sm" href="{{ route('admin.faq.create', ['pestana' => $pestana]) }}">Agregar pregunta</a>
@endsection

@section('content')
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:22px;">
    @foreach ($pestanas as $clave => $nombre)
        <a class="tab {{ $pestana === $clave ? 'on' : '' }}" data-pestana-panel="{{ $clave }}"
           href="{{ route('admin.paginas.preguntas-frecuentes', ['pestana' => $clave]) }}">{{ $nombre }} · {{ $cuantas[$clave] ?? 0 }}</a>
    @endforeach
</div>

@if ($pagina && ! $pagina->activo)
    <div class="alert alert-info" style="margin-bottom:18px;">
        La página está oculta: no se ve en el sitio. Se muestra desde Páginas sueltas → «{{ $pagina->titulo }}».
    </div>
@endif

@if ($preguntas->isEmpty())
    <div class="card" style="padding:26px;" data-faq-panel-vacia>
        <p style="margin:0 0 8px;font-weight:700;">Esta pestaña todavía no tiene preguntas.</p>
        <p class="helper" style="margin:0;">
            Mientras esté vacía, la página no la muestra: sólo salen las pestañas con alguna pregunta visible.
            Agrega la primera con «Agregar pregunta», o pasa aquí alguna de la otra pestaña.
        </p>
    </div>
@else
    <p class="helper" style="margin:-6px 0 12px;">Arrastra las filas para cambiar el orden. Las secciones salen en el orden de su primera pregunta.</p>

    <div x-data="filasOrdenables({{ Js::from(route('admin.faq.orden')) }})">
        <p class="helper" x-show="guardando" x-cloak style="margin:-6px 0 10px;">Guardando el orden…</p>
        <p class="helper" x-show="error" x-cloak x-text="error" style="margin:-6px 0 10px;color:var(--rosa);"></p>

        <x-panel.tabla :filas="$preguntas" :columnas="6" que="preguntas">
            <x-slot:cabecera>
                <th style="width:30px;"><span class="visualmente-oculto">Orden</span></th>
                <x-panel.columna clase="col-id">ID</x-panel.columna>
                <th>Sección</th>
                <th>Pregunta</th>
                <th>Se ve</th>
                <th></th>
            </x-slot:cabecera>

            @foreach ($preguntas as $p)
                <tr data-fila="{{ $p->id }}" draggable="true"
                    x-on:dragstart="empezar($event, {{ $p->id }})"
                    x-on:dragover.prevent="sobre($event, {{ $p->id }})"
                    x-on:drop.prevent="terminar()"
                    x-on:dragend="terminar()">
                    <td style="cursor:grab;color:var(--gris);" aria-hidden="true">⣿</td>
                    <x-panel.id :valor="$p->id" />
                    <td class="helper">{{ $p->seccion ?? '—' }}</td>
                    <td style="font-weight:600;">{{ $p->pregunta }}</td>
                    <td><span class="insignia insignia-{{ $p->activo ? 'si' : 'no' }}">{{ $p->activo ? 'Sí' : 'No' }}</span></td>
                    <td class="col-acciones">
                        <a class="btn btn-outline btn-sm" href="{{ route('admin.faq.edit', $p) }}">Editar</a>

                        <form method="POST" action="{{ route('admin.faq.mover', $p) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm" data-mover-pregunta>Pasar a {{ $pestanas[$p->otraPestana()] }}</button>
                        </form>

                        <form method="POST" action="{{ route('admin.faq.alternar', $p) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm">{{ $p->activo ? 'Ocultar' : 'Mostrar' }}</button>
                        </form>

                        <x-panel.confirmar
                            :accion="route('admin.faq.destroy', $p)"
                            :titulo="'Eliminar «'.Str::limit($p->pregunta, 40).'»'"
                            texto="Desaparece de la página y no se puede recuperar. Si sólo quieres que no se vea, usa «Ocultar»."
                            confirmar="Sí, eliminar"
                            boton="Borrar" />
                    </td>
                </tr>
            @endforeach
        </x-panel.tabla>
    </div>
@endif
@endsection
