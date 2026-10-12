@extends('layouts.admin')
@section('title', 'Crear usuario')
@section('miga', 'Crear usuario')

{{--
    Crear un usuario, en su propia pantalla (tanda del 12/10). Antes era un
    formulario fijo a la derecha del listado, que le quitaba ancho a la tabla
    —la columna de acciones salía cortada— y en el teléfono no se podía usar.

    Los campos y las reglas son los de siempre: `UserController::store`. Si
    algo no valida, `back()` vuelve aquí con lo escrito y sus avisos; si se
    crea, vuelve al listado.
--}}

@php
    // El rol viaja en la URL, como en el listado y la ficha: es lo que le dice
    // al menú bajo qué nodo marcar la pantalla. También deja el rol elegido.
    $rol = in_array(request('rol'), ['admin', 'organizer'], true) ? request('rol') : null;
    $volver = route('admin.users.index', array_filter(['rol' => $rol]));
@endphp

@if (! $rol)
    @section('migaPadre', 'Usuarios')
@endif

@section('actions')
    <a class="btn btn-outline btn-sm" href="{{ $volver }}">← Volver a usuarios</a>
@endsection

@section('content')
<div style="max-width:560px;margin:0 auto;">
    <form method="POST" action="{{ route('admin.users.store') }}" class="card"
          style="padding:26px;display:flex;flex-direction:column;gap:14px;"
          data-crear-usuario
          x-data="organizacionDeUsuario({ rol: @js(old('role', $rol ?? 'organizer')), buscarOrg: @js(old('org_nombre', '')) })">
        @csrf
        <div class="seclabel">Nuevo usuario</div>

        <div>
            <label class="helper" for="u-name" style="display:block;margin-bottom:6px;font-weight:600;">Nombre</label>
            <input class="fld @error('name') is-invalid @enderror" type="text" id="u-name" name="name" value="@viejo('name')" required>
            @error('name') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="helper" for="u-email" style="display:block;margin-bottom:6px;font-weight:600;">Correo</label>
            <input class="fld @error('email') is-invalid @enderror" type="email" id="u-email" name="email" value="@viejo('email')" required>
            @error('email') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="helper" for="u-password" style="display:block;margin-bottom:6px;font-weight:600;">Contraseña</label>
            <input class="fld @error('password') is-invalid @enderror" type="password" id="u-password" name="password" required autocomplete="new-password">
            <span class="helper">Mínimo 8 caracteres.</span>
            @error('password') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="helper" for="u-role" style="display:block;margin-bottom:6px;font-weight:600;">Rol</label>
            <select class="fld" id="u-role" name="role" x-model="rol">
                <option value="organizer">Organizador</option>
                <option value="admin">Administración</option>
            </select>
        </div>
        @include('admin.users._organizacion')

        <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:6px;">
            <a class="btn btn-outline" href="{{ $volver }}">Cancelar</a>
            <button type="submit" class="btn btn-primary">Crear usuario</button>
        </div>
    </form>
</div>
@endsection
