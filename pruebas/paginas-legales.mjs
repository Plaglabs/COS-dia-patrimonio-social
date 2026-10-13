// La Política de privacidad y los Términos y condiciones (13/10).
//
//   1 · El pie lleva a las dos, junto a Preguntas frecuentes.
//   2 · Cada una abre con su título, sus secciones y lo que dice el documento
//       del cliente; la privacidad ya no es el texto de relleno.
//   3 · Las dos se abren en el panel desde su nodo de Páginas y se pueden
//       guardar: se guarda cada una tal cual y se comprueba que el contenido
//       sobrevive al viaje (títulos, enlaces, correo), sin perder nada.
//
// No cambia nada: guarda lo mismo que había.
//   node pruebas/paginas-legales.mjs        (necesita Chrome; no contra producción)
import puppeteer from 'puppeteer-core';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(70)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const PAGINAS = {
  privacidad: { titulo: 'Política de privacidad', h2: 8, h3: 5, frases: ['Última actualización: 9 de octubre de 2026', '1. Quiénes somos', 'No vendemos tus datos personales.', 'Puedes agregar tu teléfono de manera opcional'] },
  'terminos-y-condiciones': { titulo: 'Términos y condiciones', h2: 10, h3: 0, frases: ['Última actualización: 9 de octubre de 2026', '1. Quiénes somos y a quiénes se aplican estos términos', 'legislación de la República de Chile'] },
};

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });

const leer = (slug) => p.evaluate(() => {
  const cuerpo = document.querySelector('.pagina-texto');
  return {
    h1: document.querySelector('article h1')?.textContent.trim(),
    h2: cuerpo?.querySelectorAll('h2').length ?? 0,
    h3: cuerpo?.querySelectorAll('h3').length ?? 0,
    texto: cuerpo?.innerText ?? '',
    correos: [...(cuerpo?.querySelectorAll('a[href^="mailto:"]') ?? [])].length,
    privacidad: [...(cuerpo?.querySelectorAll('a[href="/privacidad"]') ?? [])].length,
    fuera: [...(cuerpo?.querySelectorAll('a[href^="https://voluntariadoschile.cl"]') ?? [])].every((a) => a.target === '_blank'),
  };
});

const comprobar = async (slug, cuando) => {
  const esperado = PAGINAS[slug];
  const r = await p.goto(`${B}/${slug}`, { waitUntil: 'networkidle0' });
  const l = await leer();
  di(`${cuando} /${slug} responde con su título`, r.status() === 200 && l.h1 === esperado.titulo, `${r.status()} ${l.h1}`);
  di(`${cuando} con sus ${esperado.h2} secciones y ${esperado.h3} subsecciones`, l.h2 === esperado.h2 && l.h3 === esperado.h3, `${l.h2} / ${l.h3}`);
  di(`${cuando} con el texto del documento`, esperado.frases.every((f) => l.texto.includes(f)), esperado.frases.find((f) => ! l.texto.includes(f)) ?? '');
  di(`${cuando} el correo de contacto como enlace`, l.correos >= 1, `${l.correos}`);
  di(`${cuando} los enlaces a Voluntariados Chile en otra pestaña`, l.fuera);
  if (slug === 'terminos-y-condiciones') di(`${cuando} «Política de Privacidad» lleva a /privacidad`, l.privacidad === 2, `${l.privacidad}`);
  if (slug === 'privacidad') di(`${cuando} ya no es el texto de relleno`, ! l.texto.includes('marcador de posición'));
};

try {
  t('1 · El pie');
  await p.goto(`${B}/`, { waitUntil: 'networkidle0' });
  const pie = await p.$$eval('footer a.textlink', (as) => as.map((a) => [a.textContent.trim(), a.getAttribute('href')]));
  const enlace = (texto) => pie.find(([t]) => t === texto)?.[1] ?? '';
  di('«Política de privacidad» → /privacidad', enlace('Política de privacidad').endsWith('/privacidad'));
  di('**«Términos y condiciones» → /terminos-y-condiciones**', enlace('Términos y condiciones').endsWith('/terminos-y-condiciones'), JSON.stringify(pie.map(([t]) => t)));
  di('junto a los que ya estaban (Preguntas frecuentes sigue)', enlace('Preguntas frecuentes').endsWith('/preguntas-frecuentes'));

  t('2 · Las páginas');
  for (const slug of Object.keys(PAGINAS)) await comprobar(slug, '');

  t('3 · En el panel: se abren y se guardan sin perder nada');
  await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle0' });
  await p.type('[name="email"]', ADMIN);
  await p.type('[name="password"]', CLAVE_ADMIN);
  await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

  for (const [slug, ruta, nodo] of [['privacidad', '/admin/paginas/privacidad', 'Política de privacidad'], ['terminos-y-condiciones', '/admin/paginas/terminos-y-condiciones', 'Términos y condiciones']]) {
    const r = await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle0' });
    const form = await p.evaluate(() => ({
      titulo: document.querySelector('[name="titulo"]')?.value,
      contenido: document.querySelector('[name="contenido"]')?.value ?? '',
      activo: [...document.querySelectorAll('nav a, aside a')].find((a) => a.getAttribute('aria-current') === 'page' || a.classList.contains('activo') || a.classList.contains('is-active'))?.textContent.trim(),
    }));
    di(`el panel abre «${nodo}» con su contenido`, r.status() === 200 && form.titulo === PAGINAS[slug].titulo && form.contenido.includes('<h2>'), `${r.status()} ${form.titulo}`);
    const menu = await p.$$eval('a', (as, n) => as.some((a) => a.textContent.trim() === n && a.getAttribute('href')?.includes('/admin/paginas/')), nodo);
    di(`«${nodo}» está en el menú de Páginas`, menu);

    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle0' }), p.evaluate(() => {
      const f = document.querySelector('[name="contenido"]').form;
      (f.querySelector('button[type="submit"].btn-primary') ?? f.querySelector('button[type="submit"]')).click();
    })]);
    const aviso = await p.evaluate(() => document.querySelector('.panel-flash, .alert-ok, .alert')?.textContent.trim() ?? '');
    di(`guardar «${nodo}» desde el panel funciona`, ! /error|corrige/i.test(aviso), aviso.slice(0, 60));
    await comprobar(slug, 'tras guardar:');
  }
} finally {
  await nav.close();
}

console.log(`\n  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
