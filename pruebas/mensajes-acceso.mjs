// Los dos avisos de un acceso fallido (13/10): con un correo que tiene cuenta
// y la contraseña mala, el aviso habla de la contraseña; con un correo sin
// cuenta, dice que ese usuario no existe. En las tres puertas: /admin/login,
// /mi-cuenta/login y el acceso del wizard.
//
// Y lo que no tenía que cambiar: el rol equivocado con la contraseña buena
// sigue llevando a la otra puerta, entrar bien sigue entrando, y el bloqueo
// sigue saltando y diciéndolo.
//
// Por HTTP, sin Chrome. Borra al terminar las filas de access_logs que deja.
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const BASE = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-e', q], { encoding: 'utf8' });

const NADIE = `nadie-${Date.now()}@prueba.test`;
const CONTRASENA = /La contraseña no es correcta/;
const NO_EXISTE = /No existe ningún usuario con ese correo/;

let fallos = 0;
const di = (que, ok, detalle = '') => {
  if (! ok) fallos++;
  console.log(`${ok ? 'OK ' : 'MAL'}  ${que}${ok ? '' : `   «${detalle}»`}`);
};

// Cada intento con sus propias cookies, como una visita nueva.
function sesion() {
  const jar = new Map();
  return {
    guardar(r) { for (const c of (r.headers.getSetCookie?.() ?? [])) { const [kv] = c.split(';'); const i = kv.indexOf('='); jar.set(kv.slice(0, i), kv.slice(i + 1)); } },
    get cookies() { return [...jar].map(([k, v]) => `${k}=${v}`).join('; '); },
  };
}

/** Un intento en /admin/login o /mi-cuenta/login: devuelve el aviso pintado. */
async function formulario(ruta, email, password) {
  const s = sesion();
  const r0 = await fetch(`${BASE}${ruta}`, { headers: { cookie: s.cookies } }); s.guardar(r0);
  const _token = (await r0.text()).match(/name="_token"\s+value="([^"]+)"/)[1];

  const r = await fetch(`${BASE}${ruta}`, { method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded', cookie: s.cookies },
    body: new URLSearchParams({ _token, email, password }).toString(), redirect: 'manual' });
  s.guardar(r);

  const destino = r.headers.get('location') ?? '';
  if (! destino.endsWith(ruta)) return { entro: true, destino, msg: '' };

  const html = await (await fetch(`${BASE}${ruta}`, { headers: { cookie: s.cookies } })).text();
  const msg = html.match(/class="field-error">([^<]+)</)?.[1]?.trim() ?? '';
  return { entro: false, destino, msg, html };
}

/** Un intento por el acceso del wizard, que contesta JSON. */
async function wizard(email, password) {
  const s = sesion();
  const r0 = await fetch(`${BASE}/publicar-actividad`, { headers: { cookie: s.cookies } }); s.guardar(r0);
  const token = (await r0.text()).match(/name="csrf-token"\s+content="([^"]+)"/)[1];

  const r = await fetch(`${BASE}/publicar-actividad/entrar`, { method: 'POST',
    headers: { 'content-type': 'application/json', accept: 'application/json', 'x-csrf-token': token, cookie: s.cookies },
    body: JSON.stringify({ email, password }) });
  const datos = await r.json().catch(() => ({}));
  return { status: r.status, msg: datos.errors?.email?.[0] ?? '' };
}

const limpiar = () => sql(`DELETE FROM access_logs WHERE email IN ('${ADMIN.toLowerCase()}', '${ORG.toLowerCase()}', '${NADIE}');`);
limpiar();

console.log('— /admin/login');
let r = await formulario('/admin/login', ADMIN, 'estanoes');
di('Correo de admin y contraseña mala: habla de la contraseña', CONTRASENA.test(r.msg), r.msg);
r = await formulario('/admin/login', NADIE, 'estanoes');
di('Correo sin cuenta: ese usuario no existe', NO_EXISTE.test(r.msg), r.msg);
r = await formulario('/admin/login', ORG, 'estanoes');
di('Correo de organizador y contraseña mala: también la contraseña (el correo existe)', CONTRASENA.test(r.msg), r.msg);
r = await formulario('/admin/login', ORG, CLAVE_ORG);
di('Organizador con su contraseña buena: sigue el aviso de la otra puerta', /cuenta de organización/.test(r.msg), r.msg);
r = await formulario('/admin/login', ADMIN.toUpperCase(), 'estanoes');
di('El correo en mayúsculas cuenta como el mismo', CONTRASENA.test(r.msg), r.msg);
r = await formulario('/admin/login', ADMIN, CLAVE_ADMIN);
di('Admin con su contraseña: entra', r.entro && r.destino.endsWith('/admin'), r.destino);

console.log('— /mi-cuenta/login');
r = await formulario('/mi-cuenta/login', ORG, 'estanoes');
di('Correo de organizador y contraseña mala: habla de la contraseña', CONTRASENA.test(r.msg), r.msg);
r = await formulario('/mi-cuenta/login', NADIE, 'estanoes');
di('Correo sin cuenta: ese usuario no existe', NO_EXISTE.test(r.msg), r.msg);
r = await formulario('/mi-cuenta/login', ADMIN, CLAVE_ADMIN);
di('Admin con su contraseña buena: sigue el aviso de la otra puerta', /cuenta de administración/.test(r.msg), r.msg);
r = await formulario('/mi-cuenta/login', ORG, CLAVE_ORG);
di('Organizador con su contraseña: entra', r.entro, r.destino);

console.log('— acceso del wizard');
let w = await wizard(ORG, 'estanoes');
di('Contraseña mala: habla de la contraseña (422)', w.status === 422 && CONTRASENA.test(w.msg), `${w.status} ${w.msg}`);
w = await wizard(NADIE, 'estanoes');
di('Correo sin cuenta: ese usuario no existe (422)', w.status === 422 && NO_EXISTE.test(w.msg), `${w.status} ${w.msg}`);

console.log('— el bloqueo sigue igual');
limpiar();
for (let i = 1; i <= 5; i++) await formulario('/mi-cuenta/login', NADIE, `mala-${i}`);
r = await formulario('/mi-cuenta/login', NADIE, 'mala-6');
di('Cinco fallos con un correo sin cuenta: al sexto, bloqueado', /Demasiados intentos/.test(r.msg), r.msg);
for (let i = 1; i <= 5; i++) await formulario('/mi-cuenta/login', ORG, `mala-${i}`);
r = await formulario('/mi-cuenta/login', ORG, CLAVE_ORG);
di('Cinco fallos con un correo con cuenta: bloqueado aunque la contraseña sea buena', ! r.entro && /Demasiados intentos/.test(r.msg), r.msg);
di('Y el bloqueo no deja ver si existe', ! CONTRASENA.test(r.msg) && ! NO_EXISTE.test(r.msg), r.msg);

limpiar();
console.log(`\n${fallos ? `${fallos} MAL` : 'Todo bien'}`);
process.exit(fallos ? 1 : 0);
