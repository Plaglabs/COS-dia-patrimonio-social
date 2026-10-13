<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La Política de privacidad y los Términos y condiciones (13/10), con el
 * contenido de los documentos del cliente («Politica_de_Privacidad_DPS_2026…»
 * y «Terminos_y_Condiciones_DPS_2026…», última actualización 9 de octubre de
 * 2026), con el mismo tratamiento que tuvo Preguntas frecuentes el 09/10: una
 * página suelta (`pages`) por documento, que después se edita desde el panel
 * (Páginas → Política de privacidad / Términos y condiciones).
 *
 * - **Privacidad ya existía** con un texto de relleno: se le CAMBIA el
 *   contenido por el del documento, lo que hubiera. Es lo que pidió el
 *   cliente al mandarlo, y no hay forma de deshacerlo desde aquí.
 * - **Términos y condiciones es nueva**, y sólo se crea si no existe ya (ni
 *   en la papelera): si alguien la creó a mano en el panel, manda lo suyo.
 *
 * Los títulos de sección van en <h2> y las subsecciones en <h3>, que es lo
 * que pinta `.pagina-texto` de app.css.
 */
return new class extends Migration
{
    public function up(): void
    {
        $privacidad = [
            'titulo' => 'Política de privacidad',
            'meta_descripcion' => 'Cómo tratamos los datos personales de quienes participan, organizan o comparten en el Día del Patrimonio Social.',
            'contenido' => <<<'HTML'
<p><strong>Día del Patrimonio Social | www.diadelpatrimoniosocial.cl</strong></p>
<p>Última actualización: 9 de octubre de 2026</p>
<p><strong>Tu privacidad y tus datos personales son importantes</strong></p>
<p>En el Día del Patrimonio Social queremos que puedas participar, compartir y promover iniciativas solidarias con confianza. Por eso, cuidamos tus datos personales y los utilizamos para gestionar las actividades de la plataforma. Aquí te explicamos qué información recopilamos cuando creas una cuenta, organizas una actividad, te inscribes o compartes evaluaciones y fotografías; quiénes pueden acceder a ella y cómo ejercer tus derechos.</p>
<h2>1. Quiénes somos</h2>
<p>El Día del Patrimonio Social es una iniciativa impulsada por la Comunidad de Organizaciones Solidarias. La plataforma www.diadelpatrimoniosocial.cl es administrada por la Corporación de Organizaciones Solidarias, responsable del tratamiento de los datos personales que gestiona a través del sitio.</p>
<p>Puedes escribirnos a <a href="mailto:diadelpatrimoniosocial@comunidad-org.cl">diadelpatrimoniosocial@comunidad-org.cl</a> para consultar sobre el uso de tus datos o ejercer tus derechos.</p>
<h2>2. Qué datos recopilamos según cómo participas</h2>
<h3>Si creas una cuenta</h3>
<p>Solicitamos un correo electrónico válido y una contraseña para crear tu cuenta, iniciar sesión y administrar tu acceso. También tratamos la información necesaria para mantener la seguridad y el funcionamiento de la cuenta.</p>
<h3>Si publicas una actividad</h3>
<p>Recopilamos los datos de la actividad y de la organización que la realiza, así como la información de contacto entregada durante su registro. Usamos estos datos para gestionar y difundir la actividad y comunicarnos con quien la organiza. Si proporcionas voluntariamente un correo de contacto público, la ficha podrá mostrar la opción «Escribir a quien organiza», para que las personas puedan realizar consultas.</p>
<h3>Si te inscribes para participar en una actividad</h3>
<p>Solicitamos tu nombre y correo electrónico, y registramos si indicas que eres mayor o menor de edad. Puedes agregar tu teléfono de manera opcional. Estos datos permiten gestionar tu inscripción y facilitar la coordinación de la actividad.</p>
<p>La Comunidad de Organizaciones Solidarias y la organización responsable de la actividad pueden acceder a estos datos. La organización los utiliza para administrar las inscripciones y comunicarse con quienes participarán en su actividad.</p>
<h3>Si respondes una evaluación</h3>
<p>Cada actividad puede contar con una encuesta de evaluación accesible mediante un código QR. Recopilamos las respuestas y los datos que entregues en el formulario para conocer la experiencia de participación y mejorar las actividades. La organización que realizó la actividad puede consultar sus resultados de evaluación de forma anonimizada.</p>
<h3>Si compartes fotografías</h3>
<p>Cuando respondas la encuesta de evaluación de la actividad donde participaste, puedes subir fotografías voluntariamente desde tu dispositivo o desde las opciones de selección de archivos disponibles. Las fotografías podrán ser vistas por la organización que realizó la actividad a través de su espacio privado. Subir una fotografía no implica autorizar su difusión pública.</p>
<p>Si marcas la casilla de autorización de uso de imagen, permites que las imágenes para las que exista autorización válida se utilicen para comunicar y difundir el Día del Patrimonio Social, incluidas sus publicaciones en sitios web, redes sociales y materiales de comunicación vinculados a la iniciativa. Cuando una fotografía no tiene autorización de difusión, se identifica como tal en el espacio del organizador.</p>
<p>Si una fotografía incluye a otras personas identificables, su difusión debe respetar también los derechos y autorizaciones que correspondan a esas personas, especialmente si aparecen niños, niñas o adolescentes.</p>
<h2>3. Si buscas oportunidades de voluntariado</h2>
<p>El catálogo del Día del Patrimonio Social muestra oportunidades de Voluntariados Chile. Sus tarjetas pueden incluir el nombre de la organización, información resumida de la actividad, comuna, fecha o convocatoria continua y un botón «Postular».</p>
<p>Al seleccionar «Postular», sales de nuestro sitio y accedes a Voluntariados Chile, donde puedes revisar la oportunidad e iniciar el proceso de inscripción. Los datos que entregues al postular se gestionan en esa plataforma, conforme a su propia política de privacidad y sus términos y condiciones.</p>
<p>Para mantener el catálogo, Voluntariados Chile comparte mediante una integración técnica información de las oportunidades y organizaciones, incluidos datos de contacto de sus responsables para gestión interna. El nombre y correo de la persona de contacto de una oportunidad no se muestran en las tarjetas públicas del Día del Patrimonio Social. La integración descrita no incluye los datos de las personas que postulan.</p>
<p>Política de privacidad de Voluntariados Chile: <a href="https://voluntariadoschile.cl/politica-de-privacidad" target="_blank" rel="noopener">https://voluntariadoschile.cl/politica-de-privacidad</a></p>
<h2>4. Para qué usamos tus datos y con quién los compartimos</h2>
<ul>
  <li>Crear y administrar cuentas, actividades e inscripciones, y enviar comunicaciones necesarias para su gestión.</li>
  <li>Publicar y difundir actividades y oportunidades de participación, con los datos destinados a esa finalidad.</li>
  <li>Recopilar evaluaciones, elaborar resultados y mejorar la experiencia de participación.</li>
  <li>Gestionar fotografías y su eventual difusión, según las autorizaciones aplicables.</li>
  <li>Mantener el funcionamiento y la seguridad de la plataforma y cumplir obligaciones legales.</li>
</ul>
<p>Compartimos los datos de inscripción con la organización que realiza la actividad correspondiente. También pueden intervenir proveedores tecnológicos que prestan servicios necesarios para operar la plataforma y autoridades competentes cuando exista una obligación legal. No vendemos tus datos personales.</p>
<p>El tratamiento se sustenta, según su finalidad, en el consentimiento que otorgues, la gestión de los servicios que solicitas y las demás bases permitidas por la legislación aplicable. Las comunicaciones promocionales y el uso público de imágenes requieren la autorización correspondiente.</p>
<h2>5. Cuánto tiempo conservamos tus datos</h2>
<p>Conservamos tus datos mientras sean necesarios para administrar tu cuenta, tus inscripciones, las actividades y las demás finalidades informadas, o durante los plazos exigidos por la ley. Cuando dejan de ser necesarios, corresponde eliminarlos o anonimizarlos, salvo que exista una razón legal para conservarlos.</p>
<h2>6. Tus derechos sobre los datos y las fotografías</h2>
<p>Conforme a la legislación chilena, puedes solicitar acceso a tus datos personales, corregir información inexacta, pedir su eliminación cuando corresponda, oponerte a determinados tratamientos, solicitar su bloqueo y ejercer el derecho a la portabilidad en los casos previstos por la ley.</p>
<p>También puedes retirar el consentimiento que hayas otorgado, incluida la autorización para utilizar tu imagen en futuras publicaciones. El retiro no afecta los tratamientos realizados legítimamente antes de tu solicitud. Si una fotografía ya fue difundida, evaluaremos las medidas de retiro que correspondan respecto de los canales bajo nuestro control.</p>
<p>Para ejercer tus derechos, escribe a <a href="mailto:diadelpatrimoniosocial@comunidad-org.cl">diadelpatrimoniosocial@comunidad-org.cl</a>, indicando tu nombre, la información o actividad relacionada y lo que solicitas. Podremos pedir antecedentes proporcionados para verificar tu identidad o representación. Responderemos dentro de los plazos legales. Cuando corresponda, puedes reclamar ante la autoridad de protección de datos competente.</p>
<h2>7. Seguridad y privacidad de niños, niñas y adolescentes</h2>
<p>Adoptamos medidas de seguridad adecuadas para proteger los datos personales frente a accesos no autorizados, pérdida, alteración o uso indebido. El acceso a la información de inscripciones y evaluaciones se limita según las funciones de cada usuario u organización.</p>
<p>Si una persona menor de edad participa o aparece en fotografías, el tratamiento de sus datos y la autorización para difundir su imagen se sujetan a las reglas especiales de protección de niños, niñas y adolescentes y a las autorizaciones que correspondan según la ley.</p>
<h2>8. Cambios a esta política y contacto</h2>
<p>Podemos actualizar esta política para reflejar cambios en la plataforma o en la normativa. Publicaremos la versión vigente en este sitio, indicando la fecha de actualización. Cuando un nuevo tratamiento requiera tu consentimiento, lo solicitaremos según corresponda.</p>
<p>Responsable: Corporación de Organizaciones Solidarias (Comunidad de Organizaciones Solidarias).</p>
<p>Correo: <a href="mailto:diadelpatrimoniosocial@comunidad-org.cl">diadelpatrimoniosocial@comunidad-org.cl</a></p>
<p>Sitio web: www.diadelpatrimoniosocial.cl</p>
HTML,
            'updated_at' => now(),
        ];

        if (DB::table('pages')->where('slug', 'privacidad')->exists()) {
            DB::table('pages')->where('slug', 'privacidad')->update($privacidad);
        } else {
            DB::table('pages')->insert($privacidad + ['slug' => 'privacidad', 'activo' => true, 'created_at' => now()]);
        }

        if (DB::table('pages')->where('slug', 'terminos-y-condiciones')->exists()) {
            return;
        }

        DB::table('pages')->insert([
            'titulo' => 'Términos y condiciones',
            'slug' => 'terminos-y-condiciones',
            'meta_descripcion' => 'Cómo usar la plataforma del Día del Patrimonio Social y qué responsabilidades asumes al publicar o inscribirte en una actividad.',
            'contenido' => <<<'HTML'
<p><strong>Día del Patrimonio Social | www.diadelpatrimoniosocial.cl</strong></p>
<p>Última actualización: 9 de octubre de 2026</p>
<p><strong>Participa, comparte y organiza con confianza</strong></p>
<p>El Día del Patrimonio Social reúne actividades solidarias y comunitarias impulsadas por distintas organizaciones. Estos términos explican cómo utilizar la plataforma, qué responsabilidades asumes al publicar o inscribirte en una actividad y cómo funcionan las oportunidades de voluntariado.</p>
<h2>1. Quiénes somos y a quiénes se aplican estos términos</h2>
<p>El Día del Patrimonio Social es una iniciativa impulsada por la Comunidad de Organizaciones Solidarias. El sitio www.diadelpatrimoniosocial.cl es administrado por la Corporación de Organizaciones Solidarias (en adelante, «la Comunidad»).</p>
<p>Estos términos se aplican a las personas que visitan el sitio, crean una cuenta, publican actividades, se inscriben, responden evaluaciones o comparten fotografías. Al utilizar las funcionalidades que requieren registro, debes conocer y aceptar estos términos.</p>
<h2>2. Si creas una cuenta</h2>
<p>Para publicar una actividad o inscribirte en las actividades gestionadas directamente en el sitio, necesitas una cuenta con correo electrónico válido y contraseña.</p>
<p>Debes entregar información veraz, mantener tus datos actualizados y proteger tus credenciales. Si actúas en nombre de una organización, debes contar con autorización para representarla. No está permitido utilizar cuentas de otras personas ni acceder a información sin autorización.</p>
<h2>3. Si organizas y publicas una actividad</h2>
<p>Pueden publicar actividades las organizaciones de la sociedad civil, empresas, instituciones educativas, organismos públicos y otras agrupaciones que participen en esta celebración. La inscripción y publicación de actividades en la plataforma es gratuita.</p>
<p>Quien organiza es responsable de planificar, ejecutar y financiar su actividad, informar correctamente sus características, fecha, lugar, cupos, requisitos y condiciones de participación, y comunicar oportunamente cualquier cambio o cancelación.</p>
<p>Al publicar, autorizas a la Comunidad a mostrar y difundir la información de la actividad en el sitio y en los canales de comunicación vinculados al Día del Patrimonio Social. Debes contar con los derechos y autorizaciones necesarios sobre los textos, fotografías, logotipos y otros materiales que compartas.</p>
<p>Si agregas un correo de contacto público, este podrá mostrarse mediante la opción «Escribir a quien organiza». Utiliza únicamente una dirección cuya publicación esté autorizada.</p>
<p>Si tu actividad recibe inscripciones a través de la plataforma, podrás acceder a los datos de las personas inscritas en esa actividad para coordinar su participación. Debes utilizarlos solo para ese fin, mantenerlos protegidos y no incorporarlos a comunicaciones promocionales ajenas a la actividad sin una base legal que lo permita.</p>
<h2>4. Si te inscribes para participar</h2>
<p>Puedes consultar las actividades disponibles y, cuando corresponda, inscribirte a través de tu cuenta. La inscripción permite a la organización gestionar tu participación y contactarte sobre la actividad.</p>
<p>Cada actividad es organizada y ejecutada por la entidad que aparece identificada en su publicación. Los cupos, requisitos, horarios, condiciones de acceso y eventuales cambios dependen de esa organización. Inscribirte no garantiza que la actividad se realice si debe modificarse o cancelarse.</p>
<p>Te pedimos respetar las indicaciones de convivencia y seguridad comunicadas por quien organiza. Cuando participen niños, niñas o adolescentes, deben observarse las autorizaciones y medidas de protección que correspondan.</p>
<h2>5. Si buscas o publicas oportunidades de voluntariado</h2>
<p>Las convocatorias para incorporar voluntarios se registran en nuestra plataforma aliada Voluntariados Chile. El Día del Patrimonio Social muestra oportunidades autorizadas para su difusión, con información resumida de la organización, actividad, ubicación y fechas.</p>
<p>Al seleccionar «Postular», serás dirigido a Voluntariados Chile. Allí podrás revisar la oportunidad completa, iniciar sesión y realizar tu postulación, conforme a los términos y la política de privacidad de ese servicio. La postulación no se realiza en el sitio del Día del Patrimonio Social.</p>
<p>La información de las oportunidades llega a nuestro catálogo mediante una integración entre plataformas. Esta integración no incluye los datos de las personas postulantes. Los datos de contacto utilizados para coordinación interna no se muestran automáticamente en las tarjetas públicas.</p>
<p>Puedes consultar las condiciones de Voluntariados Chile en <a href="https://voluntariadoschile.cl/terminos-y-condiciones" target="_blank" rel="noopener">https://voluntariadoschile.cl/terminos-y-condiciones</a> y su política de privacidad en <a href="https://voluntariadoschile.cl/politica-de-privacidad" target="_blank" rel="noopener">https://voluntariadoschile.cl/politica-de-privacidad</a>.</p>
<h2>6. Si respondes evaluaciones o compartes fotografías</h2>
<p>Las actividades pueden contar con un código QR para responder una encuesta de evaluación. Las respuestas ayudan a conocer la experiencia de participación y mejorar futuras actividades; las organizaciones pueden consultar resultados de evaluación anonimizados.</p>
<p>Compartir fotografías es voluntario. Las imágenes pueden quedar disponibles en el espacio privado de la organización que realizó la actividad, pero subir una fotografía no significa autorizar su publicación o difusión.</p>
<p>La difusión de fotografías en el sitio, redes sociales u otros materiales del Día del Patrimonio Social requiere las autorizaciones correspondientes. Si aparecen otras personas identificables, debes respetar sus derechos y contar con las autorizaciones aplicables, especialmente cuando se trate de niños, niñas o adolescentes.</p>
<p>Conservas los derechos que te correspondan sobre los contenidos que aportes. Al compartirlos para una actividad, permites su recepción y gestión dentro de las funciones descritas; cualquier uso público de imágenes personales se rige por la autorización específica otorgada y por nuestra <a href="/privacidad">Política de Privacidad</a>.</p>
<h2>7. Uso responsable de la plataforma</h2>
<p>No está permitido publicar información falsa o engañosa, contenidos ilícitos o que vulneren derechos de terceros; suplantar identidades; utilizar datos de participantes para fines no autorizados; enviar mensajes masivos no solicitados; ni intentar acceder sin permiso a cuentas, registros o sistemas.</p>
<p>La Comunidad podrá revisar publicaciones y restringir o retirar contenidos o accesos cuando existan motivos fundados relacionados con incumplimientos de estos términos, protección de las personas, seguridad del sitio o exigencias legales, procurando actuar de manera proporcional.</p>
<h2>8. Responsabilidades y funcionamiento del sitio</h2>
<p>La Comunidad administra la plataforma para facilitar la difusión de actividades y la conexión entre organizaciones y participantes. Cada organización es responsable de la ejecución de las actividades que publica, incluidas sus condiciones de realización, permisos, seguridad y coordinación con participantes.</p>
<p>Procuramos mantener el sitio disponible y actualizado, aunque pueden producirse interrupciones por mantenimiento o causas técnicas. Estos términos no limitan los derechos ni las responsabilidades que la legislación chilena establece como irrenunciables.</p>
<h2>9. Datos personales y privacidad</h2>
<p>El tratamiento de los datos personales que entregas al crear una cuenta, publicar actividades, inscribirte, responder encuestas o compartir fotografías se explica en nuestra <a href="/privacidad">Política de Privacidad</a>, disponible en el sitio.</p>
<p>La aceptación de estos términos no reemplaza las autorizaciones específicas que puedan ser necesarias, por ejemplo, para la difusión pública de imágenes o comunicaciones promocionales.</p>
<h2>10. Cambios, contacto y legislación aplicable</h2>
<p>Podemos actualizar estos términos cuando cambien las funcionalidades del sitio o las normas aplicables. Publicaremos la versión vigente e indicaremos su fecha de actualización. Cuando corresponda, solicitaremos una nueva aceptación.</p>
<p>Para consultas sobre el uso de la plataforma, escríbenos a <a href="mailto:diadelpatrimoniosocial@comunidad-org.cl">diadelpatrimoniosocial@comunidad-org.cl</a>.</p>
<p>Estos términos se rigen por la legislación de la República de Chile, respetando los derechos que correspondan a las personas usuarias.</p>
HTML,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Quita Términos y condiciones sólo si sigue como la dejó esta migración.
     * La privacidad no se puede devolver: su texto de antes no se guardó.
     */
    public function down(): void
    {
        DB::table('pages')->where('slug', 'terminos-y-condiciones')
            ->where('titulo', 'Términos y condiciones')
            ->where('created_at', DB::raw('updated_at'))
            ->delete();
    }
};
