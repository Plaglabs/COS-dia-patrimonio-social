<?php

use App\Services\SanitizadorHtml;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las preguntas frecuentes de organizaciones, desde el documento del cliente
 * («FINAL_Preguntas_frecuentes_Dia_del_Patrimonio_Social_2026_Organizadores»),
 * a la pestaña «Organizaciones», que quedó vacía el 12/10.
 *
 * Son 35 de las 41 del documento, en sus 6 secciones. Las otras 6 —todas de
 * «1. Información general»— repiten pregunta con la pestaña de personas y no
 * se duplican: se quedan sólo allí.
 *
 * Un párrafo del documento venía partido («…en el kit de difusión.» / «para la
 * difusión posterior…»): aquí va unido en uno.
 *
 * **Sólo si la pestaña sigue vacía**: si la ONG ya escribió alguna pregunta de
 * organizaciones desde el panel, no se toca nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('faq_preguntas')->where('pestana', 'organizaciones')->exists()) {
            return;
        }

        $limpiar = app(SanitizadorHtml::class);
        $ahora = now();

        foreach (json_decode(self::PREGUNTAS, true, flags: JSON_THROW_ON_ERROR) as $i => $p) {
            DB::table('faq_preguntas')->insert([
                'pestana' => 'organizaciones',
                'seccion' => $p['seccion'],
                'pregunta' => $p['pregunta'],
                // El mismo filtro que aplica el panel al guardar.
                'respuesta' => $limpiar->limpiar($p['respuesta']),
                'orden' => $i + 1,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        $preguntas = array_column(json_decode(self::PREGUNTAS, true), 'pregunta');

        DB::table('faq_preguntas')->where('pestana', 'organizaciones')->whereIn('pregunta', $preguntas)->delete();
    }

    private const PREGUNTAS = <<<'JSON'
[
 {
  "seccion": "1. Información general",
  "pregunta": "¿Por qué mi organización o empresa debería sumarse al Día del Patrimonio Social?",
  "respuesta": "<p>Los días 4 y 5 de diciembre son el momento del año en que hacemos visible lo que miles de personas y organizaciones construyen todos los días: una red de confianza, apoyo y acción colectiva.</p><p>Unirnos desde distintos sectores nos permite contar con un marco común para articular, medir y comunicar el impacto de iniciativas propias y colaborativas que aportan al desarrollo sostenible del país. Como organizador, además, puedes usar gratuitamente la plataforma para publicar y administrar actividades, convocar participantes cuando corresponda, recibir evaluaciones y acceder a herramientas de difusión.</p>"
 },
 {
  "seccion": "1. Información general",
  "pregunta": "¿Hay que pagar para participar como organizador?",
  "respuesta": "<p>No. Publicar una actividad y utilizar las funcionalidades de la plataforma es gratuito. La organización y gestión de la actividad, incluidos los recursos que requiera, son responsabilidad de quien la organiza.</p>"
 },
 {
  "seccion": "2. Inscripción y publicación de actividades",
  "pregunta": "¿Qué tipos de organizaciones pueden participar como organizadores de actividades?",
  "respuesta": "<p>Pueden participar organizaciones sin fines de lucro, empresas o instituciones privadas, instituciones educativas, municipalidades u organismos públicos y otros tipos de organizaciones o agrupaciones que quieran compartir una actividad solidaria o comunitaria.</p>"
 },
 {
  "seccion": "2. Inscripción y publicación de actividades",
  "pregunta": "¿Cómo inscribo una actividad?",
  "respuesta": "<p>Ingresa a www.diadelpatrimoniosocial.cl y selecciona “Quiero organizar actividades” o el botón “Publica tu actividad”. Luego indica si solo deseas difundir tu actividad o si necesitas convocar voluntarios, selecciona tu tipo de organización, crea tu acceso con correo electrónico y contraseña, completa la información de tu organización y de la actividad, y envíala a revisión. Una vez aprobada, será visible en la sección de actividades y estará lista para que la difundas.</p>"
 },
 {
  "seccion": "2. Inscripción y publicación de actividades",
  "pregunta": "¿Qué necesito para crear mi perfil de organizador?",
  "respuesta": "<p>Debes crear un acceso con correo electrónico y contraseña y completar la información de tu organización. Te recomendamos tener a mano su logo en formato PNG o JPG, con un peso máximo de 500 KB.</p>"
 },
 {
  "seccion": "2. Inscripción y publicación de actividades",
  "pregunta": "¿Qué información debo completar sobre mi actividad?",
  "respuesta": "<p>El formulario solicita información para que las personas comprendan de qué se trata la actividad y cómo participar. Entre otros datos, podrás indicar el nombre, descripción, formato y características de la actividad, además de la información sobre su público y modalidad de participación.</p>"
 },
 {
  "seccion": "2. Inscripción y publicación de actividades",
  "pregunta": "¿Qué pasa después de enviar mi actividad?",
  "respuesta": "<p>La actividad será revisada y recibirás por correo electrónico el resultado. Una vez aprobada, será visible en la sección de actividades de la plataforma y podrás comenzar a difundirla. Además, puedes administrar tus actividades accediendo a tu perfil, con tu correo y contraseña.</p>"
 },
 {
  "seccion": "2. Inscripción y publicación de actividades",
  "pregunta": "¿Qué pasa si mi actividad necesita ajustes antes de publicarse?",
  "respuesta": "<p>Si durante la revisión se requieren cambios, la actividad quedará identificada en tu perfil como una actividad que necesita ajustes. Desde “Mis actividades” podrás revisarla, realizar las modificaciones solicitadas y continuar con el proceso de publicación.</p>"
 },
 {
  "seccion": "2. Inscripción y publicación de actividades",
  "pregunta": "¿Puedo publicar una actividad que ya sucedió?",
  "respuesta": "<p>Sí. Puedes difundir una actividad realizada en una fecha anterior, siempre que haya ocurrido a partir de junio de 2026 y se haya enmarcado conceptualmente en la celebración del Día del Patrimonio Social.</p>"
 },
 {
  "seccion": "2. Inscripción y publicación de actividades",
  "pregunta": "¿Tengo que organizar una actividad exclusivamente para el 4 o 5 de diciembre?",
  "respuesta": "<p>No necesariamente. Puedes inscribir una actividad solidaria o comunitaria que ya tenías planificada o crear una nueva. El Día del Patrimonio Social busca visibilizar y fortalecer acciones solidarias durante todo el año.</p>"
 },
 {
  "seccion": "2. Inscripción y publicación de actividades",
  "pregunta": "¿Qué tipo de actividades puedo publicar?",
  "respuesta": "<p>Puedes publicar actividades solidarias, comunitarias, culturales o de encuentro con la ciudadanía que aporten al bienestar de otras personas o comunidades. Por ejemplo: jornadas ambientales, recuperación de espacios comunitarios, campañas solidarias, asesorías o talleres gratuitos, actividades deportivas o culturales, acciones de cuidado de animales y otras iniciativas que fortalezcan la solidaridad y los vínculos comunitarios.</p>"
 },
 {
  "seccion": "2. Inscripción y publicación de actividades",
  "pregunta": "¿A qué me comprometo al publicar una actividad?",
  "respuesta": "<p>Te comprometes a organizar y gestionar la actividad por tus propios medios, entregar información clara y actualizada y enmarcarla en la celebración del Día del Patrimonio Social. Si recibes inscripciones, también es importante gestionar responsablemente los cupos y la información necesaria para la participación.</p>"
 },
 {
  "seccion": "3. Participación del público y convocatorias",
  "pregunta": "¿Puedo publicar una actividad abierta a la participación de personas?",
  "respuesta": "<p>Sí. Puedes publicar actividades abiertas al público con o sin inscripción previa.</p>"
 },
 {
  "seccion": "3. Participación del público y convocatorias",
  "pregunta": "¿Qué ocurre si mi actividad requiere inscripción previa?",
  "respuesta": "<p>Si eliges que las personas se inscriban previamente, podrán hacerlo desde la ficha de tu actividad y podrás administrar las inscripciones recibidas desde tu perfil de organizador.</p>"
 },
 {
  "seccion": "3. Participación del público y convocatorias",
  "pregunta": "¿Qué ocurre si mi actividad es abierta al público, pero no requiere inscripción?",
  "respuesta": "<p>En ese caso, la ficha de la actividad informará que las personas no necesitan inscribirse y que solo deben llegar para participar, de acuerdo con la fecha, horario, lugar y condiciones que hayas publicado.</p>"
 },
 {
  "seccion": "3. Participación del público y convocatorias",
  "pregunta": "¿Qué hago si mi actividad es cerrada a un público específico y solo quiero difundirla?",
  "respuesta": "<p>En el formulario, en la sección “Público de la actividad”, elige la opción “No, es una actividad cerrada a un público específico”. La ficha informará que la actividad se publica solo para difusión y que no está abierta a la participación del público general.</p>"
 },
 {
  "seccion": "3. Participación del público y convocatorias",
  "pregunta": "¿Puedo convocar voluntarios para mi actividad?",
  "respuesta": "<p>Sí. Al iniciar la publicación puedes indicar que necesitas voluntarios. La convocatoria se gestiona mediante la plataforma aliada Voluntariados Chile, para conectar a personas interesadas con oportunidades de voluntariado.</p>"
 },
 {
  "seccion": "3. Participación del público y convocatorias",
  "pregunta": "¿Cuál es la diferencia entre convocar voluntarios y recibir participantes en un panorama solidario?",
  "respuesta": "<p>En una convocatoria de voluntariado, las personas postulan para colaborar activamente en una actividad y la postulación se gestiona a través de Voluntariados Chile. En un panorama solidario, las personas participan como asistentes en una actividad abierta al público, con inscripción previa o simplemente asistiendo, según lo que defina el organizador.</p>"
 },
 {
  "seccion": "4. Administración de actividades y participantes",
  "pregunta": "¿Dónde administro mis actividades?",
  "respuesta": "<p>Ingresa a tu perfil y abre la sección “Mis actividades”. Allí podrás revisar el estado de tus actividades y acceder a las herramientas disponibles para gestionarlas.</p>"
 },
 {
  "seccion": "4. Administración de actividades y participantes",
  "pregunta": "¿Puedo editar una actividad?",
  "respuesta": "<p>Sí. Desde “Mis actividades” puedes editar la información de tus actividades cuando necesites actualizarla.</p>"
 },
 {
  "seccion": "4. Administración de actividades y participantes",
  "pregunta": "¿Puedo duplicar una actividad?",
  "respuesta": "<p>Sí. Puedes duplicar una actividad existente para crear una nueva a partir de información similar y luego ajustar los datos que correspondan.</p>"
 },
 {
  "seccion": "4. Administración de actividades y participantes",
  "pregunta": "¿Puedo eliminar una actividad?",
  "respuesta": "<p>Sí. Desde la administración de actividades puedes eliminar una actividad cuando corresponda.</p>"
 },
 {
  "seccion": "4. Administración de actividades y participantes",
  "pregunta": "¿Puedo crear una imagen para difundir mi actividad?",
  "respuesta": "<p>Sí. La plataforma permite generar una imagen de difusión con la información de tu actividad para descargarla o copiarla y compartirla en tus redes sociales y otros canales.</p>"
 },
 {
  "seccion": "4. Administración de actividades y participantes",
  "pregunta": "¿Cómo administro a las personas inscritas en mi actividad?",
  "respuesta": "<p>Si tu actividad recibe inscripciones, desde tu perfil puedes revisar a las personas inscritas, consultar la cantidad de cupos totales y disponibles y gestionar los cupos de la actividad.</p>"
 },
 {
  "seccion": "4. Administración de actividades y participantes",
  "pregunta": "¿Puedo cambiar la cantidad de cupos disponibles?",
  "respuesta": "<p>Sí. En la administración de participantes puedes editar los cupos disponibles de la actividad. La cantidad que definas se reflejará en la ficha pública y podrás ajustarla según la disponibilidad real.</p>"
 },
 {
  "seccion": "4. Administración de actividades y participantes",
  "pregunta": "¿Puedo descargar la lista de personas inscritas?",
  "respuesta": "<p>Sí. La plataforma permite exportar y descargar la lista de personas inscritas para facilitar la gestión de tu actividad.</p>"
 },
 {
  "seccion": "5. Evaluaciones, fotografías y cierre de la actividad",
  "pregunta": "¿Cómo puedo recibir evaluaciones de las personas que participaron?",
  "respuesta": "<p>Cada actividad cuenta con un código QR de evaluación. Puedes imprimirlo y dejarlo visible el día de la actividad para que las personas participantes lo escaneen y respondan la evaluación de su experiencia.</p>"
 },
 {
  "seccion": "5. Evaluaciones, fotografías y cierre de la actividad",
  "pregunta": "¿Dónde puedo revisar las evaluaciones recibidas?",
  "respuesta": "<p>En tu perfil encontrarás la sección “Evaluaciones”, donde podrás revisar las respuestas posteriores de las personas participantes y conocer su experiencia en tus actividades.</p>"
 },
 {
  "seccion": "5. Evaluaciones, fotografías y cierre de la actividad",
  "pregunta": "¿Las personas participantes pueden enviar fotografías?",
  "respuesta": "<p>Sí. A través de la evaluación, las personas participantes pueden enviar fotografías de la actividad y autorizar su uso de imagen.</p>"
 },
 {
  "seccion": "5. Evaluaciones, fotografías y cierre de la actividad",
  "pregunta": "¿Puedo descargar las fotografías recibidas?",
  "respuesta": "<p>Sí. Desde las evaluaciones podrás acceder y descargar las fotografías que hayan sido enviadas y que cuenten con la autorización de uso correspondiente.</p>"
 },
 {
  "seccion": "5. Evaluaciones, fotografías y cierre de la actividad",
  "pregunta": "¿Qué me recomiendan hacer el día de la actividad?",
  "respuesta": "<p>Si tu actividad tiene inscripción previa, revisa la lista de inscritos y los cupos antes de comenzar. Durante la actividad, deja visible el código QR de evaluación para facilitar que las personas compartan su experiencia y, si lo desean, fotografías con autorización de uso. Después, revisa las evaluaciones y utiliza los resultados y materiales autorizados para comunicar el impacto de la actividad.</p>"
 },
 {
  "seccion": "6. Difusión y continuidad",
  "pregunta": "¿Cómo puedo compartir y difundir mi actividad?",
  "respuesta": "<p>Una vez publicada, comparte el enlace de la ficha de tu actividad e invita a otras personas a sumarse. También puedes generar la imagen de difusión desde tu perfil y utilizar los materiales del <a href=\"https://drive.google.com/drive/folders/1MHkgL8Cfstr_AP19yRDhQ5Z8SUomHOaL?usp=sharing\">kit de difusión</a> del Día del Patrimonio Social en tus redes y canales de comunicación.</p>"
 },
 {
  "seccion": "6. Difusión y continuidad",
  "pregunta": "¿Cómo puedo difundir los resultados de mi actividad?",
  "respuesta": "<p>Comparte fotografías autorizadas, aprendizajes y resultados de tu actividad en tus canales de comunicación para visibilizar su aporte. Puedes enmarcar estos contenidos en el Día del Patrimonio Social y utilizar los materiales de difusión de la campaña.</p>"
 },
 {
  "seccion": "6. Difusión y continuidad",
  "pregunta": "¿Cómo puedo difundir los resultados generales del impacto del Día del Patrimonio Social?",
  "respuesta": "<p>Te invitamos a difundir los materiales que encontrarás en el <a href=\"https://drive.google.com/drive/folders/1MHkgL8Cfstr_AP19yRDhQ5Z8SUomHOaL\">kit de difusión</a> para la difusión posterior. ¡Mostremos lo mucho que logramos en beneficio de las comunidades actuando en colaboración!</p>"
 },
 {
  "seccion": "6. Difusión y continuidad",
  "pregunta": "¿Puedo seguir realizando y publicando actividades durante el año?",
  "respuesta": "<p>Sí. Aunque la celebración central de 2026 es el 4 y 5 de diciembre, el Día del Patrimonio Social busca fortalecer un movimiento solidario permanente. Todas las actividades realizadas hasta el 31 de diciembre 2026 se contarán en el impacto alcanzado este año por el Día del Patrimonio Social. Las acciones solidarias y comunitarias que se inscriban entre el 1 de enero y el 31 de diciembre de 2027, se contabilizarán en ese año.</p>"
 }
]
JSON;
};
