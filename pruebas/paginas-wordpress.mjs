// Las tres páginas que se conservan del sitio WordPress (15/10): /que-es,
// /edicion-2025 y /primera-edicion, con `a_lo_ancho`.
//
//   1 · Cada una responde con su <h1> propio dentro del contenido, sin el
//       título ni la columna de las páginas de texto, y sin imágenes rotas.
//   2 · Ninguna se sale de lado en un teléfono (390px): las galerías tienen
//       scroll, la página no.
//   3 · «¿Cómo puedo contribuir?»: cada tarjeta abre su panel y «Cerrar» lo
//       cierra; el panel trae su galería de cuatro ideas.
//   4 · Las galerías de 2024 se desplazan de lado, y el video de cada edición
//       no carga el iframe de YouTube hasta el clic.
//   5 · En el panel: se abren desde Páginas con «A lo ancho» marcado y se
//       guardan sin perder nada.
//   6 · En el home, las tarjetas de ediciones con enlace llevan a su página
//       (16/10). Necesita alguna edición con «A dónde lleva la tarjeta».
//
// Las páginas tienen que estar importadas: php artisan dps:importar-paginas-wordpress
//
// No cambia nada: guarda lo mismo que había.
//   node pruebas/paginas-wordpress.mjs        (necesita Chrome; no contra producción)
//
// DPS_CHROME cambia la ruta de Chrome, para correrla fuera de Windows.
import puppeteer from 'puppeteer-core';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = process.env.DPS_CHROME ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(70)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const PAGINAS = {
  'que-es': { h1: 'Descubre el Patrimonio Social', imagenes: 27, frases: ['Nuestro mayor patrimonio es la solidaridad', '¿Cómo puedo contribuir'] },
  'edicion-2025': { h1: 'Segunda edición del Día del Patrimonio Social', imagenes: 9, frases: ['160 organizaciones sociales', 'Todas las regiones del país alcanzadas', 'Un aumento de 91% de seguidores en redes'] },
  'primera-edicion': { h1: 'Primera edición del Día del Patrimonio Social', imagenes: 17, frases: ['Entre todos logramos cosas maravillosas', 'Súmate al Día del Patrimonio Social'] },
};

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();

// Las imágenes van con loading="lazy": se baja la página entera antes de mirarlas.
const recorrer = () => p.evaluate(async () => {
  for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 40)); }
  document.querySelectorAll('img[loading="lazy"]').forEach((i) => { i.loading = 'eager'; });
  await Promise.all([...document.images].map((i) => i.complete ? null : new Promise((r) => { i.onload = i.onerror = r; })));
});

try {
  t('1 · Las páginas, a lo ancho');
  await p.setViewport({ width: 1440, height: 1000 });
  for (const [slug, esperado] of Object.entries(PAGINAS)) {
    const r = await p.goto(`${B}/${slug}`, { waitUntil: 'networkidle0' });
    await recorrer();
    const l = await p.evaluate(() => {
      const cuerpo = document.querySelector('.pagina-ancha');
      // Las de YouTube no cuentan: son de fuera y no dependen del despliegue.
      const propias = [...(cuerpo?.querySelectorAll('img') ?? [])].filter((i) => ! i.src.includes('youtube.com'));
      return {
        h1: [...document.querySelectorAll('h1')].map((h) => h.textContent.trim()),
        articulo: !! document.querySelector('article .pagina-texto'),
        texto: cuerpo?.innerText ?? '',
        imagenes: propias.length,
        rotas: propias.filter((i) => ! i.naturalWidth).map((i) => i.getAttribute('src')),
        sinAlt: propias.filter((i) => ! i.hasAttribute('alt')).length,
      };
    });
    di(`/${slug} responde con su <h1> propio, y sólo uno`, r.status() === 200 && l.h1.length === 1 && l.h1[0] === esperado.h1, `${r.status()} ${JSON.stringify(l.h1)}`);
    di(`/${slug} sin la columna de las páginas de texto`, ! l.articulo);
    di(`/${slug} con su texto`, esperado.frases.every((f) => l.texto.includes(f)), esperado.frases.find((f) => ! l.texto.includes(f)) ?? '');
    di(`/${slug} con sus ${esperado.imagenes} imágenes, ninguna rota`, l.imagenes === esperado.imagenes && l.rotas.length === 0, `${l.imagenes} ${l.rotas.join(' ')}`);
    di(`/${slug} todas las imágenes con alt (vacío si son adorno)`, l.sinAlt === 0, `${l.sinAlt}`);
  }

  t('2 · En un teléfono no se sale de lado');
  await p.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
  for (const slug of Object.keys(PAGINAS)) {
    await p.goto(`${B}/${slug}`, { waitUntil: 'networkidle0' });
    const ancho = await p.evaluate(() => document.documentElement.scrollWidth);
    di(`/${slug} mide 390px de ancho, sin scroll lateral`, ancho <= 390, `${ancho}px`);
  }

  t('3 · ¿Cómo puedo contribuir?');
  await p.setViewport({ width: 1440, height: 1000 });
  await p.goto(`${B}/que-es`, { waitUntil: 'networkidle0' });
  const visibles = () => p.$$eval('.contribuir-panel', (ps) => ps.filter((x) => getComputedStyle(x).display !== 'none').map((x) => x.id));
  di('al entrar no hay ningún panel abierto', (await visibles()).length === 0);
  const tarjetas = await p.$$eval('.contribuir-tarjeta', (as) => as.map((a) => a.getAttribute('href')));
  di('cinco tarjetas', tarjetas.length === 5, `${tarjetas.length}`);

  // Como en WordPress: al pasar, la tarjeta crece hacia arriba (el borde de
  // abajo no se mueve) y las demás se quedan quietas.
  const cajas = () => p.$$eval('.contribuir-tarjeta', (as) => as.map((a) => {
    const r = a.getBoundingClientRect();
    return { arriba: Math.round(r.top + scrollY), abajo: Math.round(r.bottom + scrollY) };
  }));
  // Chrome sin interfaz en Linux se declara sin mouse (`hover: none`), que es
  // la rama táctil del CSS, y no hay flag ni CDP que lo cambie. Para medir la
  // de escritorio se quitan aquí las reglas `@media (hover: none)`: lo que
  // queda es exactamente lo que ve un navegador con mouse.
  const tactiles = await p.evaluate(async () => {
    // Con `npm run dev` la hoja llega de Vite, otro puerto: el navegador no deja
    // leer sus reglas. Se baja (Vite responde con CORS; `?direct` para que dé
    // el CSS y no el módulo JS que lo envuelve) y se pone en línea.
    for (const enlace of document.querySelectorAll('link[rel="stylesheet"][href*="/resources/css/"]')) {
      const estilo = document.createElement('style');
      estilo.textContent = await (await fetch(`${enlace.href}?direct`)).text();
      enlace.replaceWith(estilo);
    }
    let quitadas = 0;
    for (const hoja of document.styleSheets) {
      let reglas;
      try { reglas = [...hoja.cssRules]; } catch { continue; } // las de otro origen (Google Fonts) no se dejan leer
      for (let i = reglas.length - 1; i >= 0; i--) {
        if (reglas[i].conditionText?.replace(/\s/g, '') === '(hover:none)') { hoja.deleteRule(i); quitadas++; }
      }
    }
    return quitadas;
  });
  di('se mide la rama de escritorio (sin las reglas táctiles)', tactiles > 0, `${tactiles}`);
  await p.mouse.move(0, 0);
  const quietas = await cajas();
  await p.hover('.contribuir-tarjeta[href="#contribuir-necesitan"]');
  await new Promise((r) => setTimeout(r, 500));
  const encima = await cajas();
  const pregunta = await p.$eval('.contribuir-tarjeta[href="#contribuir-necesitan"] .contribuir-pregunta', (x) => getComputedStyle(x).opacity);
  di('al pasar, la pregunta aparece', pregunta === '1', pregunta);
  di('la tarjeta crece hacia arriba, con el borde de abajo fijo', encima[1].arriba < quietas[1].arriba - 30 && encima[1].abajo === quietas[1].abajo, `${JSON.stringify(quietas[1])} → ${JSON.stringify(encima[1])}`);
  di('las otras cuatro no se mueven', [0, 2, 3, 4].every((i) => JSON.stringify(encima[i]) === JSON.stringify(quietas[i])));
  await p.mouse.move(0, 0);

  for (const href of tarjetas) {
    await p.click(`.contribuir-tarjeta[href="${href}"]`);
    const abiertos = await visibles();
    const ideas = await p.$$eval(`${href} .galeria img`, (is) => is.length);
    di(`${href} abre su panel, solo, con cuatro ideas`, abiertos.length === 1 && `#${abiertos[0]}` === href && ideas === 4, `${abiertos} ${ideas}`);
  }
  await p.click(`${tarjetas.at(-1)} .contribuir-cerrar`);
  di('«Cerrar» lo cierra', (await visibles()).length === 0);

  t('4 · Galerías y video');
  await p.goto(`${B}/primera-edicion`, { waitUntil: 'networkidle0' });
  const galerias = await p.$$eval('.galeria--fila', (gs) => gs.map((g) => {
    const antes = g.scrollLeft;
    g.scrollLeft = 400;
    return { desborda: g.scrollWidth > g.clientWidth, seMueve: g.scrollLeft > antes };
  }));
  di('tres galerías en 2024', galerias.length === 3, `${galerias.length}`);
  di('las tres se desplazan de lado', galerias.every((g) => g.desborda && g.seMueve), JSON.stringify(galerias));

  // Las flechas: «siguiente» avanza, «anterior» retrocede.
  await p.goto(`${B}/primera-edicion`, { waitUntil: 'networkidle0' });
  for (const clave of ['2024-personas', '2024-eventos', '2024-empresas']) {
    const pos = () => p.$eval(`[data-carousel="${clave}"]`, (g) => Math.round(g.scrollLeft));
    const inicio = await pos();
    await p.click(`[data-carousel-next="${clave}"]`);
    await new Promise((r) => setTimeout(r, 600));
    const avanzo = await pos();
    await p.click(`[data-carousel-prev="${clave}"]`);
    await new Promise((r) => setTimeout(r, 600));
    const volvio = await pos();
    di(`${clave}: las flechas avanzan y retroceden`, inicio === 0 && avanzo > 0 && volvio < avanzo, `${inicio} → ${avanzo} → ${volvio}`);
  }

  // Suave y no a saltos: mientras anima, la posición pasa por varios puntos
  // intermedios, siempre hacia adelante. Aquí el navegador va por la rama
  // táctil, con snap, que es la que lo rompía.
  const muestras = await p.evaluate(async () => {
    const g = document.querySelector('[data-carousel="2024-personas"]');
    g.scrollLeft = 0;
    await new Promise((r) => setTimeout(r, 100));
    document.querySelector('[data-carousel-next="2024-personas"]').click();
    const vistas = [];
    for (let i = 0; i < 12; i++) { await new Promise((r) => setTimeout(r, 40)); vistas.push(Math.round(g.scrollLeft)); }
    return vistas;
  });
  const intermedios = new Set(muestras.filter((x) => x > 0 && x < muestras.at(-1))).size;
  const adelante = muestras.every((x, i) => i === 0 || x >= muestras[i - 1]);
  di('el desplazamiento es animado, sin saltos ni rebotes', intermedios >= 3 && adelante, muestras.join(' '));

  for (const [slug, id] of [['edicion-2025', 'e8iqqzO3s7k'], ['primera-edicion', 'n3SnnWdaIkk']]) {
    await p.goto(`${B}/${slug}`, { waitUntil: 'networkidle0' });
    const antes = await p.$$eval('iframe', (fs) => fs.length);
    await p.click('.franja-video');
    const src = await p.waitForSelector('.franja-video iframe', { timeout: 5000 }).then((f) => f.evaluate((x) => x.src)).catch(() => '');
    di(`/${slug}: el video carga sólo al hacer clic, y es el suyo`, antes === 0 && src.includes(id), src);
  }

  t('5 · En el panel: se abren y se guardan sin perder nada');
  await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle0' });
  await p.type('[name="email"]', ADMIN);
  await p.type('[name="password"]', CLAVE_ADMIN);
  await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

  await p.goto(`${B}/admin/contenido/paginas?por_pagina=100`, { waitUntil: 'networkidle0' });
  const enlaces = await p.$$eval('a[href*="/admin/contenido/paginas/"][href$="/editar"]', (as) => as.map((a) => a.href));

  for (const slug of Object.keys(PAGINAS)) {
    let form = null;
    for (const href of enlaces) {
      await p.goto(href, { waitUntil: 'networkidle0' });
      const f = await p.evaluate(() => ({
        slug: document.querySelector('[name="slug"]')?.value,
        contenido: document.querySelector('[name="contenido"]')?.value ?? '',
        ancho: document.querySelector('input[type="checkbox"][name="a_lo_ancho"]')?.checked,
      }));
      if (f.slug === slug) { form = f; break; }
    }
    di(`el panel lista /${slug} y la abre con «A lo ancho» marcado`, form?.ancho === true && form.contenido.includes('class="franja'), form ? `${form.ancho}` : 'no está');
    if (! form) continue;

    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle0' }), p.evaluate(() => {
      const f = document.querySelector('[name="contenido"]').form;
      (f.querySelector('button[type="submit"].btn-primary') ?? f.querySelector('button[type="submit"]')).click();
    })]);
    const r = await p.goto(`${B}/${slug}`, { waitUntil: 'networkidle0' });
    const h1 = await p.$$eval('h1', (hs) => hs.map((h) => h.textContent.trim()));
    di(`/${slug} sigue igual después de guardar`, r.status() === 200 && h1.length === 1 && h1[0] === PAGINAS[slug].h1, `${r.status()} ${JSON.stringify(h1)}`);
  }

  t('6 · Las tarjetas de ediciones del home');
  await p.setViewport({ width: 1440, height: 1000 });
  await p.goto(`${B}/`, { waitUntil: 'networkidle0' });
  const ediciones = await p.$$eval('a.tarjeta-edicion', (as) => as.map((a) => ({ href: a.getAttribute('href'), titulo: a.querySelector('h3')?.textContent.trim(), fuera: a.target === '_blank' })));
  di('alguna tarjeta de edición lleva a su página', ediciones.length > 0, `${ediciones.length}`);
  for (const tarjeta of ediciones) {
    const r = await p.goto(new URL(tarjeta.href, B).href, { waitUntil: 'networkidle0' });
    const h1 = await p.$$eval('h1', (hs) => hs.map((h) => h.textContent.trim()));
    di(`«${tarjeta.titulo}» → ${tarjeta.href}, en la misma pestaña, y la página existe`, r.status() === 200 && h1.length === 1 && ! tarjeta.fuera, `${r.status()} ${JSON.stringify(h1)}`);
  }
} finally {
  await nav.close();
  console.log('');
  console.log(`${ok} OK, ${mal} MAL`);
  process.exitCode = mal ? 1 : 0;
}
