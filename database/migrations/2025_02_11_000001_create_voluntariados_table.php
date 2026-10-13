<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Los voluntariados de Voluntariados Chile (13/10).
 *
 * Tabla propia y no `activities`: son de ellos, de sólo lectura, y no pasan
 * por moderación, inscripción, QR ni evaluación. Los rellena la
 * sincronización (`dps:sincronizar-voluntariados`, cada hora); las visitas
 * leen de aquí y nunca llaman a la API.
 *
 * Sus catálogos —categoría, áreas de impacto, formato, regiones— se guardan
 * con sus códigos y nombres tal cual llegan. Lo único que se traduce a lo
 * nuestro es lo que hace falta para filtrar: el formato y las regiones, en
 * columnas aparte, y lo que no se reconoce se queda sin traducir en vez de
 * romper nada. `crudo` es el elemento entero de la respuesta.
 *
 * `activo` lo apaga la sincronización cuando una oportunidad deja de llegar,
 * y sólo tras una sincronización completa y sana. No se borra nada: si vuelve
 * a aparecer con el mismo id, se reactiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voluntariados', function (Blueprint $table) {
            $table->id();
            $table->string('externo_id', 64)->unique();
            $table->string('slug')->unique();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->text('responsabilidades')->nullable();
            $table->string('estado_api', 60)->nullable();

            $table->string('organizacion_nombre')->nullable();
            $table->string('organizacion_logo_url', 1000)->nullable();
            $table->string('organizacion_tipo')->nullable();
            $table->json('areas_impacto')->nullable();

            $table->string('categoria_codigo', 60)->nullable();
            $table->string('categoria_nombre')->nullable();

            $table->string('imagen_url', 1000)->nullable();
            $table->string('enlace_postular', 1000)->nullable();
            $table->string('enlace_externo', 1000)->nullable();

            $table->string('formato_original', 60)->nullable();
            $table->string('formato', 20)->nullable();

            $table->json('regiones_originales')->nullable();
            $table->json('region_ids')->nullable();
            $table->json('comunas_originales')->nullable();

            $table->string('horario_tipo', 30)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->unsignedInteger('voluntarios_requeridos')->nullable();
            $table->json('requisitos')->nullable();

            $table->json('crudo')->nullable();
            $table->timestamp('actualizado_api_at')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('baja_at')->nullable();
            $table->timestamps();

            $table->index(['activo', 'fecha_inicio']);
        });

        Schema::create('voluntariado_sincronizaciones', function (Blueprint $table) {
            $table->id();
            $table->timestamp('empezo_at');
            $table->timestamp('termino_at')->nullable();
            $table->string('resultado', 20);
            $table->unsignedInteger('recibidas')->default(0);
            $table->unsignedInteger('altas')->default(0);
            $table->unsignedInteger('cambios')->default(0);
            $table->unsignedInteger('bajas')->default(0);
            $table->text('mensaje')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voluntariado_sincronizaciones');
        Schema::dropIfExists('voluntariados');
    }
};
