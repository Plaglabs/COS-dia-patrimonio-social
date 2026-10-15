<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Páginas que se pintan a lo ancho (15/10), para las que llegaron del sitio
 * WordPress: «Qué es», «Edición 2025» y «Primera edición». Son landings de
 * franjas de color a todo el ancho, con su propio título dentro, y no caben en
 * la columna de 760px con el <h1> encima que usan las páginas de texto.
 *
 * Por defecto apagado: las páginas que ya existen siguen igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('a_lo_ancho')->default(false)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('a_lo_ancho');
        });
    }
};
