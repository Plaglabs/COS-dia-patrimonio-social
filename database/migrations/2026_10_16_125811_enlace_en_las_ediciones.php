<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dónde lleva la tarjeta de cada edición en el home (16/10): la página de
 * esa edición, como `/edicion-2025` o `/primera-edicion`. Vacío, la tarjeta no
 * es un enlace, como hasta ahora.
 *
 * Se llena desde el panel (Ediciones): no se rellena aquí, porque qué página
 * corresponde a cada edición es contenido, no esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editions', function (Blueprint $table) {
            $table->string('enlace')->nullable()->after('imagen');
        });
    }

    public function down(): void
    {
        Schema::table('editions', function (Blueprint $table) {
            $table->dropColumn('enlace');
        });
    }
};
