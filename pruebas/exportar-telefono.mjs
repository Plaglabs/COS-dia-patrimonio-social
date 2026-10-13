// El teléfono del participante en las dos exportaciones de inscritos (13/10):
// la que baja el organizador de su actividad y Inscripciones → Exportar del
// panel, donde va justo detrás del correo.
//
// Siembra tres inscripciones en una actividad del organizador sembrado —con
// teléfono, sin teléfono y con un «teléfono» que empieza por `=`— y comprueba
// en los dos archivos que el teléfono sale tal cual, que sin él la celda va
// vacía y que **el `=` no llega como fórmula**: OpenSpout convierte en fórmula
// cualquier texto que empiece por `=`, y el teléfono es texto libre de un
// formulario público. Borra las tres inscripciones al terminar.
//
// Por HTTP, sin Chrome. Desde la raíz del repo (llama a `leer-xlsx.php`).
//   node pruebas/exportar-telefono.mjs
import { execFileSync } from 'node:child_process';
import { writeFileSync, unlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const BASE = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '--default-character-set=utf8mb4', '-e', q]).toString().trim();

let fallos = 0;
const di = (que, ok, detalle = '') => {
  if (! ok) fallos++;
  console.log(`${ok ? 'OK ' : 'MAL'}  ${que}${ok ? '' : `   «${detalle}»`}`);
};

/** Entra por un acceso y devuelve las cookies de la sesión. */
async function entrar(ruta, email, password) {
  const jar = new Map();
  const guardar = (r) => { for (const c of (r.headers.getSetCookie?.() ?? [])) { const [kv] = c.split(';'); const i = kv.indexOf('='); jar.set(kv.slice(0, i), kv.slice(i + 1)); } };
  const cookies = () => [...jar].map(([k, v]) => `${k}=${v}`).join('; ');

  const r0 = await fetch(`${BASE}${ruta}`, { headers: { cookie: cookies() } }); guardar(r0);
  const _token = (await r0.text()).match(/name="_token"\s+value="([^"]+)"/)[1];
  const r = await fetch(`${BASE}${ruta}`, { method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded', cookie: cookies() },
    body: new URLSearchParams({ _token, email, password }).toString(), redirect: 'manual' });
  guardar(r);

  return cookies();
}

/** Baja un Excel y devuelve sus filas, y si alguna celda va como fórmula. */
async function bajar(url, cookies) {
  const r = await fetch(url, { headers: { cookie: cookies } });
  const archivo = join(tmpdir(), `telefono-${Date.now()}.xlsx`);
  writeFileSync(archivo, Buffer.from(await r.arrayBuffer()));

  const filas = JSON.parse(execFileSync(PHP, ['pruebas/leer-xlsx.php', archivo], { encoding: 'utf8' }));
  const formulas = execFileSync(PHP, ['-r', `$z = new ZipArchive; $z->open($argv[1]); echo substr_count($z->getFromName('xl/worksheets/sheet1.xml'), '<f>');`, archivo], { encoding: 'utf8' }).trim();
  unlinkSync(archivo);

  return { status: r.status, filas, formulas: +formulas };
}

// Una actividad del organizador sembrado, que es quien puede bajar su lista.
const actId = sql(`select a.id from activities a join users u on u.id = a.user_id
    where u.email = '${ORG}' and a.deleted_at is null order by a.id limit 1`);

const MARCA = `tel-export-${Date.now()}`;
const CON = '+56 9 1234 5678';
const FORMULA = '=HYPERLINK("http://ejemplo.test","clic")';
const inscribir = (sufijo, telefono) => sql(`insert into registrations
    (activity_id, nombre, correo, telefono, es_mayor_edad, estado, token, created_at, updated_at)
    values (${actId}, '${MARCA} ${sufijo}', '${MARCA}-${sufijo}@prueba.test', ${telefono === null ? 'null' : `'${telefono.replace(/'/g, "''")}'`},
    1, 'pendiente', '${MARCA}-${sufijo}', now(), now()); select last_insert_id();`);

const ids = { con: inscribir('con', CON), sin: inscribir('sin', null), formula: inscribir('formula', FORMULA) };

const comprobar = (cual, { status, filas, formulas }, despuesDe) => {
  const [cab, ...datos] = filas;
  const col = cab.indexOf('Teléfono');
  const tel = (id) => String(datos.find((f) => String(f[0]) === String(id))?.[col] ?? '«no está»');

  di(`${cual}: el archivo se descarga`, status === 200, status);
  di(`${cual}: hay columna «Teléfono», detrás de «${despuesDe}»`, col > 0 && cab[col - 1] === despuesDe, cab.join(' | '));
  di(`${cual}: todas las filas tienen las mismas celdas que la cabecera`, datos.every((f) => f.length === cab.length));
  di(`${cual}: el teléfono informado sale tal cual`, tel(ids.con) === CON, tel(ids.con));
  di(`${cual}: sin teléfono, la celda va vacía`, tel(ids.sin) === '', tel(ids.sin));
  di(`${cual}: el «teléfono» con = sale como texto`, tel(ids.formula) === `'${FORMULA}`, tel(ids.formula));
  di(`${cual}: **ninguna celda va como fórmula**`, formulas === 0, `${formulas} fórmulas`);
};

try {
  const org = await entrar('/mi-cuenta/login', ORG, CLAVE_ORG);
  comprobar('Organizador', await bajar(`${BASE}/mi-cuenta/actividades/${actId}/participantes/exportar`, org), 'Correo');

  const admin = await entrar('/admin/login', ADMIN, CLAVE_ADMIN);
  comprobar('Panel', await bajar(`${BASE}/admin/inscripciones/exportar/descargar?actividad=${actId}`, admin), 'Correo');
} finally {
  sql(`delete from registrations where correo like '${MARCA}-%'`);
}

console.log(`\n${fallos ? `${fallos} MAL` : 'Todo bien'}`);
process.exit(fallos ? 1 : 0);
