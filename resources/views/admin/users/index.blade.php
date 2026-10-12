@extends('layouts.admin')
@section('title', 'Usuarios')

{{-- Sin `?rol` el menú no sabe qué nodo marcar —ese parámetro es lo que
     distingue «Administradores» de «Organizadores»— y la pantalla se quedaba
     sin migas. Con esto al menos dice dónde estás. --}}
@if (! request('rol'))
    @section('migaPadre', 'Usuarios')
    @section('miga', 'Todos')
@endif

{{-- Crear va en su propia pantalla (12/10): el formulario fijo a la derecha
     estrechaba la tabla hasta cortar la columna de acciones. --}}
@section('actions')
    <a class="btn btn-primary btn-sm" href="{{ route('admin.users.create', array_filter(['rol' => request('rol')])) }}">Crear usuario</a>
@endsection

@section('content')
<section style="min-width:0;">

        <x-panel.filtros
            buscar="Buscar por nombre o correo…">
            @if (request('rol'))
                <input type="hidden" name="rol" value="{{ request('rol') }}">
            @endif

            <select class="fld" name="estado" x-on:change="enviar()" aria-label="Estado">
                <option value="">Activos e inactivos</option>
                <option value="si" @selected(request('estado') === 'si')>Sólo los activos</option>
                <option value="no" @selected(request('estado') === 'no')>Sólo los inactivos</option>
            </select>

            <select class="fld" name="papelera" x-on:change="enviar()" aria-label="Papelera">
                @foreach (\App\Support\Papelera::OPCIONES as $valor => $texto)
                    <option value="{{ $valor }}" @selected(\App\Support\Papelera::estado(request()) === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
        </x-panel.filtros>

        <x-panel.tabla
            :filas="$usuarios"
            :columnas="7"
            que="usuarios"
            :vacio="request()->hasAny(['q', 'estado', 'papelera']) ? 'Ningún usuario coincide con el filtro.' : 'Sin usuarios.'">

            <x-slot:cabecera>
                <x-panel.columna campo="id" clase="col-id">ID</x-panel.columna>
                <x-panel.columna campo="name">Nombre</x-panel.columna>
                <x-panel.columna campo="email">Correo</x-panel.columna>
                <x-panel.columna campo="role">Rol</x-panel.columna>
                <x-panel.columna campo="is_active">Estado</x-panel.columna>
                <x-panel.columna campo="last_login_at">Última entrada</x-panel.columna>
                <th></th>
            </x-slot:cabecera>

            @foreach ($usuarios as $u)
                <tr @class(['fila-eliminada' => $u->trashed()])>
                    <x-panel.id :valor="$u->id" />

                    <td style="font-weight:600;">{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->esAdmin() ? 'Administración' : 'Organizador' }}</td>
                    <td><span class="insignia insignia-{{ $u->is_active ? 'si' : 'no' }}">{{ $u->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                    <td style="white-space:nowrap;">{{ $u->last_login_at ? \App\Support\Fecha::relativa($u->last_login_at) : 'Nunca' }}</td>

                    <td class="col-acciones">
                        @if ($u->trashed())
                            <span class="helper">Eliminado {{ \App\Support\Fecha::relativa($u->deleted_at) }}</span>
                            <form method="POST" action="{{ route('admin.users.restaurar', $u->id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm">Restaurar</button>
                            </form>
                        @else
                            {{-- El rol viaja en la URL para que el menú sepa bajo
                                 qué nodo está la ficha: «Administradores» y
                                 «Organizadores» son la misma ruta con distinto
                                 parámetro. --}}
                            <a class="btn btn-outline btn-sm" href="{{ route('admin.users.edit', [$u, 'rol' => $u->role]) }}">Editar</a>

                            <form method="POST" action="{{ route('admin.users.toggle', $u) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm">{{ $u->is_active ? 'Desactivar' : 'Activar' }}</button>
                            </form>

                            @if ($u->id !== auth()->id())
                                <x-panel.confirmar
                                    :accion="route('admin.users.destroy', $u)"
                                    :titulo="'Eliminar a «'.Str::limit($u->name, 34).'»'"
                                    texto="Deja de poder entrar y desaparece de los listados. Su rastro en accesos y correos se conserva, y se puede recuperar con el filtro de la papelera."
                                    confirmar="Sí, eliminar"
                                    boton="Borrar" />
                            @else
                                <span class="helper" title="No puedes eliminar tu propia cuenta">Tu cuenta</span>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-panel.tabla>
</section>
@endsection
