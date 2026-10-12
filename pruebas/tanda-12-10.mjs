// Tanda del 12/10 — en Chrome.
//
//   1. /actividades y el carrusel del home: por fecha de la actividad,
//      ascendente, sin las que ya pasaron; las permanentes al final del
//      listado y fuera del home; el total cuadra con lo que se ve; el
//      calendario no cambia.
//   2. Usuarios y Catálogos: la tabla a todo el ancho y crear en su propia
//      pantalla.
//   3. Exportar una organización a Excel desde su ficha, con sus cuentas.
//   4. Evaluaciones: aviso verde cuando la foto SÍ tiene autorización.
//   5. Preguntas frecuentes en dos pestañas, editables y movibles desde el
//      panel.
//
// Monta su escenario con un sello irrepetible y lo deshace al final. Toca las
// preguntas frecuentes (mueve una y la devuelve, crea una y la borra).
// No contra producción.
//
//   node pruebas/tanda-12-10.mjs        (desde la raíz del repo)
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { writeFileSync, unlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(74)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();
const json = (php) => JSON.parse(ultima(tinker(php)));

const SELLO = Date.now();
const CLAVE = 'ClaveLarga123';
const PRINCIPAL = `t12.principal.${SELLO}@ejemplo.cl`;
const SUMADA = `t12.sumada.${SELLO}@ejemplo.cl`;
const ORG = `Fundación Tanda Doce ${SELLO}`;
const T = `T12 ${SELLO}`;

/* ─────────────────────────────── escenario ── */

const D = json(
  `$z = App\\Support\\Fecha::zona(); $hoy = now($z)->startOfDay();`
  + ` $p = App\\Models\\User::create(['name' => 'Principal Doce', 'email' => '${PRINCIPAL}', 'password' => '${CLAVE}', 'role' => 'organizer', 'is_active' => true]);`
  + ` $s = App\\Models\\User::create(['name' => 'Sumada Doce', 'email' => '${SUMADA}', 'password' => '${CLAVE}', 'role' => 'organizer', 'is_active' => true]);`
  + ` $o = App\\Models\\Organization::create(['nombre' => '${ORG}', 'tipo' => 'Organización sin fines de lucro', 'activo' => true, 'correo_contacto' => 'contacto.doce@ejemplo.cl', 'descripcion' => 'Descripción de prueba']);`
  + ` $o->enlazarCuenta($p); $o->enlazarCuenta($s);`
  + ` $c = App\\Models\\Commune::where('activo', true)->first(); $base = App\\Models\\Activity::whereNotNull('published_at')->first();`
  + ` $nueva = function ($nombre, $extra) use ($base, $o, $p, $c) { $a = $base->replicate(); $a->forceFill(array_merge(['organization_id' => $o->id, 'user_id' => $p->id,`
  + ` 'titulo' => '${T} '.$nombre, 'slug' => 't12-'.$nombre.'-${SELLO}', 'estado' => 'publicada', 'published_at' => now(), 'destacada' => false, 'cerrada' => false,`
  + ` 'sin_fecha_definida' => false, 'fecha_termino' => null, 'region_id' => $c->region_id, 'commune_id' => $c->id], $extra))->save(); return $a; };`
  + ` $a = ['manana' => $nueva('manana', ['fecha_inicio' => $hoy->copy()->addDays(3)->toDateString()]),`
  + ` 'hoy' => $nueva('hoy', ['fecha_inicio' => $hoy->toDateString()]),`
  + ` 'encurso' => $nueva('encurso', ['fecha_inicio' => $hoy->copy()->subDay()->toDateString(), 'fecha_termino' => $hoy->copy()->addDay()->toDateString()]),`
  + ` 'pasada' => $nueva('pasada', ['fecha_inicio' => $hoy->copy()->subDay()->toDateString()]),`
  + ` 'permanente' => $nueva('permanente', ['fecha_inicio' => null, 'sin_fecha_definida' => true, 'destacada' => true]),`
  + ` 'pordefinir' => $nueva('pordefinir', ['fecha_inicio' => null, 'destacada' => true])];`
  + ` $e1 = App\\Models\\ActivityEvaluation::create(['activity_id' => $a['hoy']->id, 'nombre' => 'Con permiso', 'correo' => 'con.${SELLO}@ejemplo.cl', 'experiencia' => 5, 'motivacion' => 5, 'significado' => 'Bien', 'foto_autorizada' => true]);`
  + ` $e1->fotos()->create(['ruta' => 'evaluaciones/no-existe-${SELLO}-1.jpg', 'orden' => 1]);`
  + ` $e2 = App\\Models\\ActivityEvaluation::create(['activity_id' => $a['hoy']->id, 'nombre' => 'Sin permiso', 'correo' => 'sin.${SELLO}@ejemplo.cl', 'experiencia' => 4, 'motivacion' => 4, 'significado' => 'Bien', 'foto_autorizada' => false]);`
  + ` $e2->fotos()->create(['ruta' => 'evaluaciones/no-existe-${SELLO}-2.jpg', 'orden' => 1]);`
  + ` echo json_encode(['org' => $o->id, 'ids' => collect($a)->map->id, 'e1' => $e1->id, 'e2' => $e2->id, 'pasadaDia' => (int) $hoy->copy()->subDay()->day, 'mesPasada' => $hoy->copy()->subDay()->format('Y-m')]);`
);

const limpiar = () => tinker(
  `foreach (App\\Models\\Organization::withTrashed()->where('nombre', '${ORG}')->get() as $o) {`
  + ` foreach ($o->activities()->withTrashed()->get() as $a) { foreach (App\\Models\\ActivityEvaluation::where('activity_id', $a->id)->get() as $e) { $e->fotos()->delete(); $e->delete(); }`
  + ` $a->registrations()->delete(); $a->collaborators()->delete(); $a->statusLogs()->delete(); $a->terms()->detach(); $a->forceDelete(); }`
  + ` App\\Models\\User::where('organization_id', $o->id)->update(['organization_id' => null]); $o->forceDelete(); }`
  + ` foreach (App\\Models\\User::withTrashed()->where('email', 'like', 't12.%.${SELLO}@ejemplo.cl')->get() as $u) {`
  + ` App\\Models\\AccessLog::where('user_id', $u->id)->orWhere('actor_id', $u->id)->delete(); $u->forceDelete(); }`
  + ` App\\Models\\TaxonomyTerm::withTrashed()->where('nombre', 'like', 'Término T12 ${SELLO}%')->forceDelete();`
  + ` App\\Models\\FaqPregunta::where('pregunta', 'like', '%${SELLO}%')->delete();`
  + ` echo 'LIMPIO';`
);

/* ─────────────────────────────── utilidades ── */

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];

const pestana = async () => {
  const ctx = await nav.createBrowserContext();
  const p = await ctx.newPage();
  await p.setViewport({ width: 1440, height: 1000 });
  p.on('pageerror', (e) => errores.push(String(e)));
  return p;
};

const entrar = async (p, ruta, email, clave) => {
  await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', email);
  await p.type('input[type="password"]', clave);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);
};

const enviar = (p, selector) => Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.evaluate((s) => document.querySelector(s).submit(), selector)]);

/** Los ids de las fichas que enlaza la página, en el orden en que salen. */
const idsEnPagina = (p, dentro = 'body') => p.evaluate((d) => {
  const vistos = [];
  for (const a of document.querySelectorAll(`${d} a[href*="/activity/"]`)) {
    const id = Number(a.getAttribute('href').match(/\/activity\/(\d+)/)?.[1]);
    if (id && ! vistos.includes(id)) vistos.push(id);
  }
  return vistos;
}, dentro);

/** ¿Algún botón o enlace de las acciones sale cortado («Bo…»)? */
const accionesCortadas = (p) => p.evaluate(() => [...document.querySelectorAll('.col-acciones .btn, .col-acciones button')]
  .filter((b) => b.offsetParent !== null && b.scrollWidth > b.clientWidth + 1)
  .map((b) => b.textContent.trim()));

try {
  const pub = await pestana();
  const admin = await pestana();
  await entrar(admin, '/admin/login', ADMIN, CLAVE_ADMIN);

  /* ─────────────── 1 ─────────────── */
  t('1 · /actividades: por fecha, sin las pasadas');

  await pub.goto(`${B}/actividades?q=${encodeURIComponent(T)}`, { waitUntil: 'networkidle2' });
  const mias = await idsEnPagina(pub);
  const I = D.ids;
  di('salen en orden de fecha: la en curso, la de hoy, la de dentro de 3 días y la permanente',
    JSON.stringify(mias) === JSON.stringify([I.encurso, I.hoy, I.manana, I.permanente]), JSON.stringify(mias));
  di('la que ya pasó no sale', ! mias.includes(I.pasada));
  di('la que no tiene fecha sin ser permanente no sale', ! mias.includes(I.pordefinir));
  const total = await pub.evaluate(() => document.body.innerText.match(/(\d+)\s+actividad(es)?\s+publicada/)?.[1]);
  di('el total dice 4, lo que se ve', total === '4', `«${total}»`);
  const fechaPermanente = await pub.evaluate((id) => document.querySelector(`a[href*="/activity/${id}/"]`)?.closest('.act-card')?.innerText ?? '', I.permanente);
  di('la permanente dice «Todo el año», no «Por definir»', fechaPermanente.includes('Todo el año') && ! fechaPermanente.includes('Por definir'));

  // El listado entero, todas las páginas, contra la consulta.
  const esperado = json(`echo json_encode(App\\Models\\Activity::published()->abiertasAlPublico()->vigentes()->porFecha()->pluck('id'));`);
  let todos = [];
  for (let pag = 1; pag < 20; pag++) {
    await pub.goto(`${B}/actividades?page=${pag}`, { waitUntil: 'networkidle2' });
    const ids = await idsEnPagina(pub);
    if (! ids.length) break;
    todos = todos.concat(ids.filter((id) => ! todos.includes(id)));
  }
  di('el listado entero sale en el orden de la consulta, página a página', JSON.stringify(todos) === JSON.stringify(esperado), `${todos.length} de ${esperado.length}`);
  await pub.goto(`${B}/actividades`, { waitUntil: 'networkidle2' });
  const totalGeneral = await pub.evaluate(() => document.body.innerText.match(/(\d+)\s+actividad(es)?\s+publicada/)?.[1]);
  di('el contador general cuadra con las que se pueden ver', Number(totalGeneral) === esperado.length, `${totalGeneral} / ${esperado.length}`);
  const pasadas = json(`echo json_encode(App\\Models\\Activity::whereIn('id', [${todos.join(',') || 0}])->get()->filter->yaPaso()->pluck('id')->values());`);
  di('ninguna del listado ya pasó', pasadas.length === 0, JSON.stringify(pasadas));

  t('1 · el calendario no cambia');
  await pub.goto(`${B}/actividades?vista=calendario&mes=${D.mesPasada}&q=${encodeURIComponent(T)}`, { waitUntil: 'networkidle2' });
  const cal = await pub.evaluate(() => document.body.innerText);
  di('la que ya pasó sigue en su día del calendario', cal.includes(`${'T12'}`) && cal.includes('pasada'));
  di('la permanente sigue en «Disponibles todo el año»', /Disponibles todo el año[\s\S]*permanente/.test(cal));

  t('1 · el carrusel del home');
  await pub.goto(`${B}/`, { waitUntil: 'networkidle2' });
  const enHome = await pub.evaluate(() => {
    const vistos = [];
    for (const a of document.querySelectorAll('.carousel a[href*="/activity/"]')) {
      const id = Number(a.getAttribute('href').match(/\/activity\/(\d+)/)?.[1]);
      if (id && ! vistos.includes(id)) vistos.push(id);
    }
    return vistos;
  });
  const datosHome = json(`echo json_encode(App\\Models\\Activity::whereIn('id', [${enHome.join(',') || 0}])->get()->mapWithKeys(fn ($a) => [$a->id => ['f' => $a->fecha_inicio?->toDateString(), 'perm' => (bool) $a->sin_fecha_definida, 'paso' => $a->yaPaso()]]));`);
  const fechasHome = enHome.map((id) => datosHome[id]?.f);
  di('el home enseña actividades', enHome.length > 0, `${enHome.length}`);
  di('ninguna sin fecha ni permanente (las «Por definir» de antes)', enHome.every((id) => datosHome[id]?.f && ! datosHome[id]?.perm));
  di('ninguna que ya pasó', enHome.every((id) => ! datosHome[id]?.paso));
  di('en orden de fecha, ascendente', JSON.stringify(fechasHome) === JSON.stringify([...fechasHome].sort()), fechasHome.join(' '));
  di('ni la permanente ni la «por definir» destacadas a mano', ! enHome.includes(I.permanente) && ! enHome.includes(I.pordefinir));
  const textoHome = await pub.evaluate(() => [...document.querySelectorAll('.carousel')].map((c) => c.innerText).join(' '));
  di('el carrusel no dice «Por definir»', ! textoHome.includes('Por definir'));

  /* ─────────────── 2 ─────────────── */
  for (const ancho of [1440, 390]) {
    t(`2 · Usuarios a ${ancho} px`);
    await admin.setViewport({ width: ancho, height: 900 });
    await admin.goto(`${B}/admin/usuarios?rol=organizer`, { waitUntil: 'networkidle2' });
    di('sin el formulario al lado', await admin.evaluate(() => ! document.querySelector('main aside') && ! document.querySelector('[data-crear-usuario]')));
    const tabla = await admin.evaluate(() => { const w = document.querySelector('.panel-tabla'); const m = document.querySelector('main'); return w && m ? [Math.round(w.getBoundingClientRect().width), Math.round(m.clientWidth)] : null; });
    di('la tabla ocupa el ancho del contenido', tabla && tabla[0] >= tabla[1] - 80, JSON.stringify(tabla));
    const cortadas = await accionesCortadas(admin);
    di('ningún botón de acciones sale cortado', cortadas.length === 0, cortadas.join(', '));
    di('hay un botón «Crear usuario»', await admin.evaluate(() => [...document.querySelectorAll('a')].some((a) => a.textContent.trim() === 'Crear usuario' && a.href.includes('/admin/usuarios/nuevo'))));
    di('sin scroll lateral de la página', await admin.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
  }
  await admin.setViewport({ width: 1440, height: 1000 });

  t('2 · Crear usuario, en su pantalla');
  await admin.goto(`${B}/admin/usuarios/nuevo?rol=organizer`, { waitUntil: 'networkidle2' });
  di('el formulario está y nada más: sin tabla', await admin.evaluate(() => !! document.querySelector('form[data-crear-usuario]') && ! document.querySelector('.panel-tabla')));
  di('el menú marca «Organizadores»', await admin.evaluate(() => [...document.querySelectorAll('.nav-hoja')].some((a) => a.textContent.trim() === 'Organizadores' && (a.classList.contains('activo') || a.getAttribute('aria-current')))));
  const centro = await admin.evaluate(() => { const f = document.querySelector('form[data-crear-usuario]').getBoundingClientRect(); const m = document.querySelector('main').getBoundingClientRect(); return Math.abs((f.left - m.left) - (m.right - f.right)); });
  di('el formulario va centrado', centro < 60, `${Math.round(centro)} px de diferencia`);
  // Rebote: contraseña corta. Vuelve aquí con lo escrito.
  await admin.type('[data-crear-usuario] [name="name"]', 'Usuario Doce');
  await admin.type('[data-crear-usuario] [name="email"]', `t12.nuevo.${SELLO}@ejemplo.cl`);
  await admin.type('[data-crear-usuario] [name="password"]', 'corta');
  await admin.select('[data-crear-usuario] [name="role"]', 'admin');
  await enviar(admin, 'form[data-crear-usuario]');
  di('si no valida, se queda en crear con el aviso', admin.url().includes('/admin/usuarios/nuevo') && !! await admin.$('[data-crear-usuario] .field-error'));
  di('…y con lo escrito', await admin.$eval('[data-crear-usuario] [name="email"]', (i) => i.value) === `t12.nuevo.${SELLO}@ejemplo.cl`);
  await admin.$eval('[data-crear-usuario] [name="password"]', (i) => { i.value = ''; });
  await admin.type('[data-crear-usuario] [name="password"]', CLAVE);
  await enviar(admin, 'form[data-crear-usuario]');
  di('al guardar vuelve a la tabla', /\/admin\/usuarios\?rol=admin/.test(admin.url()), admin.url().replace(B, ''));
  di('…con el aviso de creado', (await admin.evaluate(() => document.body.innerText)).includes('Usuario creado'));
  di('…y el usuario existe', json(`echo json_encode(App\\Models\\User::where('email', 't12.nuevo.${SELLO}@ejemplo.cl')->exists());`) === true);
  await admin.goto(`${B}/admin/usuarios/nuevo`, { waitUntil: 'networkidle2' });
  const cancelar = await admin.evaluate(() => [...document.querySelectorAll('main a')].find((a) => a.textContent.trim() === 'Cancelar')?.getAttribute('href'));
  di('«Cancelar» vuelve a la tabla', /\/admin\/usuarios$/.test(cancelar ?? ''), cancelar);

  t('2 · Catálogos');
  await admin.goto(`${B}/admin/taxonomias?grupo=publico`, { waitUntil: 'networkidle2' });
  di('sin el formulario al lado', await admin.evaluate(() => ! document.querySelector('main aside')));
  di('con el botón «Agregar término»', await admin.evaluate(() => [...document.querySelectorAll('a')].some((a) => a.textContent.trim() === 'Agregar término' && a.href.includes('/admin/taxonomias/nuevo?grupo=publico'))));
  di('ningún botón de acciones sale cortado', (await accionesCortadas(admin)).length === 0);
  await admin.goto(`${B}/admin/taxonomias/nuevo?grupo=publico`, { waitUntil: 'networkidle2' });
  di('el menú marca «Públicos»', await admin.evaluate(() => [...document.querySelectorAll('.nav-hoja')].some((a) => a.textContent.trim() === 'Públicos' && (a.classList.contains('activo') || a.getAttribute('aria-current')))));
  await admin.type('[data-crear-termino] [name="nombre"]', `Término T12 ${SELLO}`);
  await enviar(admin, 'form[data-crear-termino]');
  di('agrega el término y vuelve a su catálogo', admin.url().includes('/admin/taxonomias?grupo=publico')
    && json(`echo json_encode(App\\Models\\TaxonomyTerm::where('nombre', 'Término T12 ${SELLO}')->value('grupo'));`) === 'publico');

  /* ─────────────── 3 ─────────────── */
  t('3 · Exportar la organización');
  await admin.goto(`${B}/admin/organizaciones/${D.org}/editar`, { waitUntil: 'networkidle2' });
  const enlace = await admin.$eval('[data-exportar-organizacion]', (a) => ({ href: a.href, texto: a.textContent.trim(), descarga: a.hasAttribute('data-descarga') }));
  di('la ficha tiene «Exportar a Excel»', enlace.texto === 'Exportar a Excel' && enlace.descarga);
  const b64 = await admin.evaluate(async (u) => {
    const r = await fetch(u);
    const buf = new Uint8Array(await r.arrayBuffer());
    let s = ''; for (const x of buf) s += String.fromCharCode(x);
    return { tipo: r.headers.get('content-type'), datos: btoa(s), nombre: r.headers.get('content-disposition') };
  }, enlace.href);
  di('descarga un .xlsx', b64.tipo.includes('spreadsheetml') && /\.xlsx/.test(b64.nombre), b64.nombre);
  const archivo = join(tmpdir(), `org-${SELLO}.xlsx`);
  writeFileSync(archivo, Buffer.from(b64.datos, 'base64'));
  const hojas = JSON.parse(execFileSync(PHP, ['pruebas/leer-xlsx.php', archivo, '--hojas'], { encoding: 'utf8' }));
  unlinkSync(archivo);
  di('dos hojas: Organización y Cuentas', JSON.stringify(Object.keys(hojas)) === JSON.stringify(['Organización', 'Cuentas']), Object.keys(hojas).join(', '));
  const ficha = Object.fromEntries((hojas['Organización'] ?? []).slice(1));
  di('la ficha trae nombre, tipo, correo y descripción', ficha['Nombre'] === ORG && ficha['Tipo'] === 'Organización sin fines de lucro'
    && ficha['Correo de contacto'] === 'contacto.doce@ejemplo.cl' && ficha['Descripción'] === 'Descripción de prueba');
  di('…y la cuenta principal', ficha['Cuenta principal'] === PRINCIPAL, ficha['Cuenta principal']);
  const cuentas = hojas['Cuentas'] ?? [];
  di('Cuentas: cabeceras con nombre, correo y fecha en que se sumó', JSON.stringify(cuentas[0]?.slice(0, 4)) === JSON.stringify(['Cuenta', 'Nombre', 'Correo', 'Se sumó']));
  di('la principal primero, con su nombre y correo', cuentas[1]?.[0] === 'Principal' && cuentas[1]?.[1] === 'Principal Doce' && cuentas[1]?.[2] === PRINCIPAL);
  di('después la asociada', cuentas[2]?.[0] === 'Asociada' && cuentas[2]?.[2] === SUMADA && cuentas.length === 3);
  const hoyIso = json(`echo json_encode(now(App\\Support\\Fecha::zona())->toDateString());`);
  di('con la fecha en que se sumó', cuentas[2]?.[3] === hoyIso, cuentas[2]?.[3]);

  // Pulsarlo de verdad: se marca ocupado y se suelta solo al llegar el
  // archivo (cookie de AvisaDescargaLista), no se queda colgado.
  const cdp = await admin.createCDPSession();
  await cdp.send('Browser.setDownloadBehavior', { behavior: 'allowAndName', downloadPath: tmpdir(), browserContextId: admin.browserContext().id });
  await admin.click('[data-exportar-organizacion]');
  const marcado = await admin.$eval('[data-exportar-organizacion]', (a) => a.classList.contains('esta-cargando'));
  let suelto = false;
  for (let i = 0; i < 40 && ! suelto; i++) {
    await esperar(250);
    suelto = await admin.$eval('[data-exportar-organizacion]', (a) => ! a.classList.contains('esta-cargando'));
  }
  di('al pulsarlo se marca y se suelta solo en segundos', marcado && suelto);

  /* ─────────────── 4 ─────────────── */
  t('4 · Evaluaciones: el aviso verde');
  const org = await pestana();
  await entrar(org, '/mi-cuenta/login', PRINCIPAL, CLAVE);
  await org.goto(`${B}/mi-cuenta/evaluaciones`, { waitUntil: 'networkidle2' });
  const avisosOrg = await org.evaluate(() => ({
    verde: [...document.querySelectorAll('[data-foto-autorizada]')].map((e) => [e.textContent.trim(), getComputedStyle(e).color]),
    rojo: [...document.querySelectorAll('[data-foto-sin-autorizar]')].map((e) => e.textContent.trim()),
  }));
  di('organizador: la autorizada lleva aviso verde', avisosOrg.verde.length === 1 && avisosOrg.verde[0][0].startsWith('Con autorización de difusión') && avisosOrg.verde[0][1] === 'rgb(13, 107, 100)', JSON.stringify(avisosOrg.verde));
  di('organizador: la otra sigue con el rojo', avisosOrg.rojo.length === 1 && avisosOrg.rojo[0].startsWith('Sin autorización de difusión'));
  await admin.goto(`${B}/admin/evaluaciones?q=${SELLO}`, { waitUntil: 'networkidle2' });
  const avisosAdm = await admin.evaluate(() => ({
    verde: document.querySelectorAll('[data-foto-autorizada]').length,
    rojo: document.querySelectorAll('[data-foto-sin-autorizar]').length,
  }));
  di('administrador: uno verde y uno rojo', avisosAdm.verde >= 1 && avisosAdm.rojo >= 1, JSON.stringify(avisosAdm));

  /* ─────────────── 5 ─────────────── */
  t('5 · Preguntas frecuentes: la página');
  await pub.goto(`${B}/preguntas-frecuentes`, { waitUntil: 'networkidle2' });
  const faq = await pub.evaluate(() => ({
    pestanas: [...document.querySelectorAll('[data-faq-pestana]')].map((a) => [a.textContent.trim(), a.classList.contains('activa')]),
    preguntas: document.querySelectorAll('.faq details').length,
    secciones: [...document.querySelectorAll('.faq h2')].map((h) => h.textContent.trim()),
    enlaces: [...document.querySelectorAll('.faq a')].map((a) => [a.href, a.target]),
  }));
  di('dos pestañas, Personas elegida', JSON.stringify(faq.pestanas) === JSON.stringify([['Personas', true], ['Organizaciones', false]]), JSON.stringify(faq.pestanas));
  di('Personas: las 32 preguntas', faq.preguntas === 32, String(faq.preguntas));
  di('…en sus 4 secciones, en orden', faq.secciones.length === 4 && faq.secciones[0].startsWith('1.') && faq.secciones[3].startsWith('4.'), faq.secciones.join(' / '));
  di('…con tildes', faq.secciones[0] === '1. Información general');
  di('los enlaces a otros sitios abren en otra pestaña', faq.enlaces.length > 0 && faq.enlaces.filter(([h]) => ! h.startsWith(B)).every(([, tg]) => tg === '_blank'));
  const alto = await pub.$eval('.faq details', (d) => d.getBoundingClientRect().height);
  await pub.click('.faq details summary');
  await esperar(150);
  di('una pregunta se abre al pulsarla', await pub.$eval('.faq details', (d) => d.getBoundingClientRect().height) > alto + 20);

  await pub.click('[data-faq-pestana="organizaciones"]');
  await pub.waitForNetworkIdle();
  const vacia = await pub.evaluate(() => ({
    url: location.search,
    activa: document.querySelector('[data-faq-pestana].activa')?.textContent.trim(),
    aviso: document.querySelector('[data-faq-vacia]')?.innerText ?? '',
    preguntas: document.querySelectorAll('.faq details').length,
    alto: Math.round(document.querySelector('[data-faq-vacia]')?.getBoundingClientRect().height ?? 0),
  }));
  di('Organizaciones: la pestaña se marca y viaja en la URL', vacia.activa === 'Organizaciones' && vacia.url === '?pestana=organizaciones');
  di('vacía, con su aviso y sin preguntas', vacia.preguntas === 0 && vacia.aviso.includes('Pronto publicaremos') && vacia.alto > 40, vacia.aviso.replace(/\s+/g, ' '));
  di('…y un enlace de vuelta a Personas', await pub.evaluate(() => [...document.querySelectorAll('[data-faq-vacia] a')].some((a) => a.textContent.includes('personas'))));
  await pub.setViewport({ width: 390, height: 844 });
  for (const q of ['', '?pestana=organizaciones']) {
    await pub.goto(`${B}/preguntas-frecuentes${q}`, { waitUntil: 'networkidle2' });
    di(`a 390 px sin scroll lateral ${q || '(personas)'}`, await pub.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
  }
  await pub.setViewport({ width: 1440, height: 1000 });

  t('5 · Preguntas frecuentes: el panel');
  await admin.goto(`${B}/admin/paginas/preguntas-frecuentes`, { waitUntil: 'networkidle2' });
  const panel = await admin.evaluate(() => ({
    filas: document.querySelectorAll('tbody tr[data-fila]').length,
    pestanas: [...document.querySelectorAll('[data-pestana-panel]')].map((a) => a.textContent.trim()),
    marcado: [...document.querySelectorAll('.nav-hoja')].some((a) => a.textContent.trim() === 'Preguntas frecuentes' && (a.classList.contains('activo') || a.getAttribute('aria-current'))),
  }));
  di('Personas: 32 filas', panel.filas === 32, String(panel.filas));
  di('las dos pestañas con su cuenta', panel.pestanas.join(' | ') === 'Personas · 32 | Organizaciones · 0', panel.pestanas.join(' | '));
  di('el menú marca «Preguntas frecuentes»', panel.marcado);
  await admin.goto(`${B}/admin/paginas/preguntas-frecuentes?pestana=organizaciones`, { waitUntil: 'networkidle2' });
  di('Organizaciones vacía lo explica', !! await admin.$('[data-faq-panel-vacia]'));

  // Mover la primera a Organizaciones, mirar la página, y devolverla.
  const primera = json(`echo json_encode(App\\Models\\FaqPregunta::de('personas')->ordenadas()->first()->only(['id', 'pregunta', 'orden']));`);
  await admin.goto(`${B}/admin/paginas/preguntas-frecuentes`, { waitUntil: 'networkidle2' });
  await Promise.all([admin.waitForNavigation({ waitUntil: 'networkidle2' }),
    admin.evaluate((id) => document.querySelector(`tr[data-fila="${id}"] [data-mover-pregunta]`).closest('form').submit(), primera.id)]);
  di('«Pasar a Organizaciones» la mueve', json(`echo json_encode(App\\Models\\FaqPregunta::find(${primera.id})->pestana);`) === 'organizaciones');
  await pub.goto(`${B}/preguntas-frecuentes?pestana=organizaciones`, { waitUntil: 'networkidle2' });
  const enOrg = await pub.evaluate(() => [...document.querySelectorAll('.faq summary')].map((s) => s.textContent.trim()));
  di('la página la enseña en Organizaciones', enOrg.length === 1 && enOrg[0] === primera.pregunta, enOrg.join(' | '));
  await pub.goto(`${B}/preguntas-frecuentes`, { waitUntil: 'networkidle2' });
  di('…y Personas se queda con 31', await pub.evaluate(() => document.querySelectorAll('.faq details').length) === 31);
  // De vuelta, desde su formulario: cambiar la pestaña.
  await admin.goto(`${B}/admin/paginas/preguntas-frecuentes/${primera.id}/editar`, { waitUntil: 'networkidle2' });
  await admin.select('[data-form-pregunta] [name="pestana"]', 'personas');
  await enviar(admin, 'form[data-form-pregunta]');
  const vuelta = json(`echo json_encode(App\\Models\\FaqPregunta::find(${primera.id})->only(['pestana', 'orden']));`);
  di('cambiar la pestaña en el formulario la devuelve (al final)', vuelta.pestana === 'personas', JSON.stringify(vuelta));
  tinker(`App\\Models\\FaqPregunta::whereKey(${primera.id})->update(['orden' => ${primera.orden}]); echo 'ok';`);
  await pub.goto(`${B}/preguntas-frecuentes`, { waitUntil: 'networkidle2' });
  di('…y vuelve a ser la primera de la página', await pub.$eval('.faq summary', (s) => s.textContent.trim()) === primera.pregunta);

  // Crear una en Organizaciones con el editor de texto, verla y borrarla.
  await admin.goto(`${B}/admin/paginas/preguntas-frecuentes/nueva?pestana=organizaciones`, { waitUntil: 'networkidle2' });
  di('crear viene con la pestaña elegida', await admin.$eval('[name="pestana"]', (s) => s.value) === 'organizaciones');
  await admin.type('[data-form-pregunta] [name="seccion"]', 'Para organizaciones');
  await admin.type('[data-form-pregunta] [name="pregunta"]', `¿Pregunta de prueba ${SELLO}?`);
  await admin.click('.editor-rico-campo');
  await admin.keyboard.type('Respuesta con ');
  await admin.click('.editor-rico-barra button[title="Negrita"]');
  await admin.keyboard.type('negrita');
  await enviar(admin, 'form[data-form-pregunta]');
  di('se agrega y vuelve a su pestaña', admin.url().includes('pestana=organizaciones'));
  await pub.goto(`${B}/preguntas-frecuentes?pestana=organizaciones`, { waitUntil: 'networkidle2' });
  const nueva = await pub.evaluate(() => ({ h2: document.querySelector('.faq h2')?.textContent.trim(), html: document.querySelector('.faq details > div')?.innerHTML ?? '' }));
  di('sale en la página con su sección', nueva.h2 === 'Para organizaciones');
  di('…y la negrita del editor', nueva.html.includes('<strong>negrita</strong>'), nueva.html.slice(0, 80));
  await pub.goto(`${B}/preguntas-frecuentes?pestana=organizaciones`, { waitUntil: 'networkidle2' });
  // Una respuesta vacía no se guarda.
  await admin.goto(`${B}/admin/paginas/preguntas-frecuentes/nueva`, { waitUntil: 'networkidle2' });
  await admin.type('[data-form-pregunta] [name="pregunta"]', `¿Sin respuesta ${SELLO}?`);
  await enviar(admin, 'form[data-form-pregunta]');
  di('sin respuesta rebota con su aviso', admin.url().includes('/nueva') && (await admin.evaluate(() => document.body.innerText)).includes('Escribe la respuesta'));
  tinker(`App\\Models\\FaqPregunta::where('pregunta', 'like', '%${SELLO}%')->delete(); echo 'ok';`);

  t('Errores de JavaScript');
  di('ninguno en ninguna pantalla', errores.length === 0, errores.slice(0, 3).join(' | '));
} catch (e) {
  mal++;
  console.log('*** ERROR ***', e);
} finally {
  await nav.close();
  console.log('', limpiar());
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
