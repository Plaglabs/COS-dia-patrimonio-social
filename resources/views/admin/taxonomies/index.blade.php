@extends('layouts.admin')
@section('title', 'Catálogos')

{{--
    Los cuatro catálogos: temas, características, públicos y accesibilidad.

    Un término en uso no se borra, se apaga: está enganchado a actividades
    publicadas, y borrarlo las dejaría sin esa etiqueta y sin forma de
    recuperarla. Por eso la columna «En uso» está en la tabla y no escondida: es
    la que decide si el botón de borrar sirve de algo.
--}}

{{-- Agregar va en su propia pantalla (12/10), como Crear usuario. --}}
@section('actions')
    <a class="btn btn-primary btn-sm" href="{{ route('admin.taxonomies.create', ['grupo' => $grupo]) }}">Agregar término</a>
@endsection

@section('content')
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:22px;">
    @foreach ($grupos as $clave => $label)
        <a class="tab {{ $grupo === $clave ? 'on' : '' }}" href="{{ route('admin.taxonomies.index', ['grupo' => $clave]) }}">{{ $label }}</a>
    @endforeach
</div>

@if ($limite)
    <div class="alert alert-info" style="margin-bottom:20px;">
        Una actividad puede elegir hasta <strong>{{ $limite }}</strong> {{ Str::lower($grupos[$grupo]) }}.
    </div>
@endif

<div style="min-width:0;">

        <x-panel.filtros
            buscar="Buscar un término…"
            :exportar="[
                'xlsx' => route('admin.taxonomies.exportar', ['grupo' => $grupo, 'formato' => 'xlsx'] + request()->query()),
                'csv' => route('admin.taxonomies.exportar', ['grupo' => $grupo, 'formato' => 'csv'] + request()->query()),
            ]">
            <input type="hidden" name="grupo" value="{{ $grupo }}">

            <select class="fld" name="estado" x-on:change="enviar()" aria-label="Se ofrece">
                <option value="">Se ofrecen y no</option>
                <option value="si" @selected(request('estado') === 'si')>Sólo los que se ofrecen</option>
                <option value="no" @selected(request('estado') === 'no')>Sólo los apagados</option>
            </select>

            <select class="fld" name="papelera" x-on:change="enviar()" aria-label="Papelera">
                @foreach (\App\Support\Papelera::OPCIONES as $valor => $texto)
                    <option value="{{ $valor }}" @selected(\App\Support\Papelera::estado(request()) === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
        </x-panel.filtros>

        @unless ($puedeReordenar)
            <p class="helper" style="margin:-6px 0 14px;">
                Para cambiar el orden arrastrando, quita los filtros.
            </p>
        @endunless

        <div @if ($puedeReordenar) x-data="filasOrdenables({{ Js::from(route('admin.taxonomies.orden')) }})" @endif>
            @if ($puedeReordenar)
                <p class="helper" x-show="guardando" x-cloak style="margin:-6px 0 10px;">Guardando el orden…</p>
                <p class="helper" x-show="error" x-cloak x-text="error" style="margin:-6px 0 10px;color:var(--rosa);"></p>
            @endif

            <x-panel.tabla
                :filas="$terminos"
                :columnas="$puedeReordenar ? 6 : 5"
                que="términos"
                :vacio="request()->hasAny(['q', 'estado', 'papelera']) ? 'Ningún término coincide con el filtro.' : 'Sin términos en este grupo. Agrega el primero con «Agregar término».'">

                <x-slot:cabecera>
                    @if ($puedeReordenar)
                        <th style="width:30px;"><span class="visualmente-oculto">Orden</span></th>
                    @endif
                    <x-panel.columna :campo="$puedeReordenar ? null : 'id'" clase="col-id">ID</x-panel.columna>
                    <x-panel.columna :campo="$puedeReordenar ? null : 'nombre'">Término</x-panel.columna>
                    <th class="num">En uso</th>
                    <x-panel.columna :campo="$puedeReordenar ? null : 'activo'">Se ofrece</x-panel.columna>
                    <th></th>
                </x-slot:cabecera>

                @foreach ($terminos as $t)
                    <tr @class(['fila-eliminada' => $t->trashed()])
                        data-fila="{{ $t->id }}"
                        @if ($puedeReordenar)
                            draggable="true"
                            x-on:dragstart="empezar($event, {{ $t->id }})"
                            x-on:dragover.prevent="sobre($event, {{ $t->id }})"
                            x-on:drop.prevent="terminar()"
                            x-on:dragend="terminar()"
                        @endif>

                        @if ($puedeReordenar)
                            <td style="cursor:grab;color:var(--gris);" aria-hidden="true">⣿</td>
                        @endif

                        <x-panel.id :valor="$t->id" />

                        <td>
                            @if ($t->trashed())
                                <span style="font-weight:600;">{{ $t->nombre }}</span>
                            @else
                                <form method="POST" action="{{ route('admin.taxonomies.update', $t) }}" style="display:flex;gap:8px;align-items:center;">
                                    @csrf
                                    @method('PUT')
                                    <input class="fld" style="padding:7px 10px;font-size:13.5px;" type="text" name="nombre"
                                           value="{{ $t->nombre }}" aria-label="Nombre del término" maxlength="255" required>
                                    <input type="hidden" name="orden" value="{{ $t->orden }}">
                                    <input type="hidden" name="activo" value="{{ $t->activo ? 1 : 0 }}">
                                    <button type="submit" class="btn btn-outline btn-sm">Guardar</button>
                                </form>
                            @endif
                        </td>

                        <td class="num">{{ $t->activities_count }}</td>
                        <td><span class="insignia insignia-{{ $t->activo ? 'si' : 'no' }}">{{ $t->activo ? 'Sí' : 'No' }}</span></td>

                        <td class="col-acciones">
                            @if ($t->trashed())
                                <form method="POST" action="{{ route('admin.taxonomies.restaurar', $t->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm">Restaurar</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.taxonomies.alternar', $t) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm">{{ $t->activo ? 'Apagar' : 'Encender' }}</button>
                                </form>

                                @if ($t->activities_count === 0)
                                    <x-panel.confirmar
                                        :accion="route('admin.taxonomies.destroy', $t)"
                                        :titulo="'Eliminar «'.Str::limit($t->nombre, 40).'»'"
                                        texto="No lo usa ninguna actividad. Se puede recuperar con el filtro de la papelera."
                                        confirmar="Sí, eliminar"
                                        boton="Borrar" />
                                @else
                                    <span class="helper" title="Apágalo en vez de borrarlo">En uso</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-panel.tabla>
        </div>
</div>
@endsection
