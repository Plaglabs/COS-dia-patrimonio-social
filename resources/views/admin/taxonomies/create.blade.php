@extends('layouts.admin')
@section('title', 'Agregar término')
@section('miga', 'Agregar término')

{{--
    Agregar un término a un catálogo, en su propia pantalla (tanda del 12/10),
    como Crear usuario: el formulario fijo a la derecha del listado le quitaba
    ancho a la tabla. El grupo viaja en la URL, que es lo que le dice al menú
    qué catálogo marcar.
--}}

@section('actions')
    <a class="btn btn-outline btn-sm" href="{{ route('admin.taxonomies.index', ['grupo' => $grupo]) }}">← Volver a {{ Str::lower($grupos[$grupo]) }}</a>
@endsection

@section('content')
<div style="max-width:560px;margin:0 auto;">
    <form method="POST" action="{{ route('admin.taxonomies.store') }}" class="card"
          style="padding:26px;display:flex;flex-direction:column;gap:14px;" data-crear-termino>
        @csrf
        <input type="hidden" name="grupo" value="{{ $grupo }}">

        <div class="seclabel">Nuevo término en {{ Str::lower($grupos[$grupo]) }}</div>

        <x-panel.campo nombre="nombre" label="Nombre" reglas="required|string|max:255" />
        <x-panel.campo nombre="orden" label="Orden" tipo="number" :valor="$siguiente" reglas="nullable|integer|min:0" />

        <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:6px;">
            <a class="btn btn-outline" href="{{ route('admin.taxonomies.index', ['grupo' => $grupo]) }}">Cancelar</a>
            <button type="submit" class="btn btn-primary" data-cargando="Agregando…">Agregar</button>
        </div>
    </form>
</div>
@endsection
