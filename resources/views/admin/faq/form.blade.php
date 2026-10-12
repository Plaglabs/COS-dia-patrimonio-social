@extends('layouts.admin')
@section('title', $pregunta->exists ? 'Editar pregunta' : 'Agregar pregunta')
@section('miga', $pregunta->exists ? Str::limit($pregunta->pregunta, 32) : 'Agregar pregunta')

{{--
    Una pregunta frecuente (tanda del 12/10). La respuesta usa el mismo editor
    de texto que el home, y se limpia en el servidor con la misma lista blanca.
    Cambiar la pestaña aquí es lo mismo que «Pasar a…» del listado: la pregunta
    va al final de la otra.
--}}

@php
    $volver = route('admin.paginas.preguntas-frecuentes', ['pestana' => $pregunta->pestana]);
@endphp

@section('actions')
    <a class="btn btn-outline btn-sm" href="{{ $volver }}">← Volver a las preguntas</a>
@endsection

@section('content')
<div style="max-width:760px;margin:0 auto;">
    <form method="POST" action="{{ $pregunta->exists ? route('admin.faq.update', $pregunta) : route('admin.faq.store') }}"
          class="card" style="padding:26px;display:flex;flex-direction:column;gap:16px;"
          data-form-pregunta x-data="{ pestana: @js(old('pestana', $pregunta->pestana)) }">
        @csrf
        @if ($pregunta->exists)
            @method('PUT')
        @endif

        <label class="lbl">Pestaña
            <select class="fld @error('pestana') is-invalid @enderror" name="pestana" x-model="pestana">
                @foreach ($pestanas as $clave => $nombre)
                    <option value="{{ $clave }}" @selected(old('pestana', $pregunta->pestana) === $clave)>{{ $nombre }}</option>
                @endforeach
            </select>
            @if ($pregunta->exists)
                <span class="helper">Si la cambias, la pregunta pasa al final de la otra pestaña.</span>
            @endif
            @error('pestana') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="lbl">Sección
            <input class="fld @error('seccion') is-invalid @enderror" name="seccion" maxlength="255" autocomplete="off"
                   value="@viejo('seccion', $pregunta->seccion)" x-bind:list="'secciones-' + pestana"
                   placeholder="Ej. 1. Información general">
            <span class="helper">El título bajo el que se agrupa. Escribe el mismo de otra pregunta para ponerla junto a ella; uno nuevo abre otra sección. Vacía, va sin título.</span>
            @error('seccion') <span class="field-error">{{ $message }}</span> @enderror
        </label>
        @foreach ($pestanas as $clave => $nombre)
            <datalist id="secciones-{{ $clave }}">
                @foreach ($secciones[$clave] ?? [] as $s)
                    <option value="{{ $s }}"></option>
                @endforeach
            </datalist>
        @endforeach

        <label class="lbl">Pregunta
            <input class="fld @error('pregunta') is-invalid @enderror" name="pregunta" maxlength="500" required
                   value="@viejo('pregunta', $pregunta->pregunta)">
            @error('pregunta') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div class="lbl">Respuesta
            @include('partials.admin.editor-rico', [
                'clave' => 'respuesta',
                'valor' => old('respuesta', $pregunta->respuesta),
                'campo' => ['label' => 'Respuesta'],
            ])
            @error('respuesta') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <label style="display:flex;align-items:center;gap:10px;font-size:14.5px;">
            <input type="checkbox" name="activo" value="1" @checked(session()->hasOldInput() ? old('activo') : ($pregunta->activo ?? true))>
            Se ve en la página
        </label>

        <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
            <a class="btn btn-outline" href="{{ $volver }}">Cancelar</a>
            <button type="submit" class="btn btn-primary" data-cargando="Guardando…">{{ $pregunta->exists ? 'Guardar' : 'Agregar pregunta' }}</button>
        </div>
    </form>
</div>
@endsection
