// Las dos exportaciones de inscritos —la que baja el organizador de su
// actividad e Inscripciones → Exportar del panel— (13/10):
//
//   · el teléfono del participante, detrás del correo; vacío si no lo dio;
//   · y NINGÚN texto escrito en un formulario público llega como fórmula.
//
// Estas dos exportaciones escriben con OpenSpout directamente (no pasan por
// `Exportador`), y OpenSpout convierte en fórmula cualquier texto que empiece
// por `=`. Se siembran inscripciones con nombre, correo y teléfono que
// empiezan por `=`, una respuesta de evaluación igual, y la actividad y su
// organización se renombran un momento con `=` delante. En los dos archivos se
// comprueba que cada uno de esos textos sale como texto (con el apóstrofo) y
// que el `.xlsx` no trae ni una `<f>`. Lo deja todo como estaba.
//
// Por HTTP, sin Chrome. Desde la raíz del repo (llama a `leer-xlsx.php`).
//   node pruebas/exportar-sin-formulas.mjs        (no contra producción)
import { execFileSync } from 'node:child_process';
import { writeFileSync, unlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const BASE = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '--default-character-set=utf8mb4', '-e', q]).toString().trim();
const lit = (s) => (s === null ? 'null' : `'${String(s).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`);

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

/** Baja un Excel y devuelve sus filas, y cuántas celdas van como fórmula. */
async function bajar(url, cookies) {
  const r = await fetch(url, { headers: { cookie: cookies } });
  const archivo = join(tmpdir(), `inscritos-${Date.now()}.xlsx`);
  writeFileSync(archivo, Buffer.from(await r.arrayBuffer()));

  const filas = JSON.parse(execFileSync(PHP, ['pruebas/leer-xlsx.php', archivo], { encoding: 'utf8' }));
  const formulas = execFileSync(PHP, ['-r', `$z = new ZipArchive; $z->open($argv[1]); echo substr_count($z->getFromName('xl/worksheets/sheet1.xml'), '<f>');`, archivo], { encoding: 'utf8' }).trim();
  unlinkSync(archivo);

  return { status: r.status, filas, formulas: +formulas };
}

// Una actividad del organizador sembrado, que es quien puede bajar su lista.
const actId = sql(`select a.id from activities a join users u on u.id = a.user_id
    where u.email = '${ORG}' and a.deleted_at is null order by a.id limit 1`);
const orgId = sql(`select organization_id from activities where id = ${actId}`);
const [tituloAntes, orgAntes] = [sql(`select titulo from activities where id = ${actId}`), sql(`select nombre from organizations where id = ${orgId}`)];

const MARCA = `xl-${Date.now()}`;
const CON = '+56 9 1234 5678';
const F = (que) => `=HYPERLINK("http://ejemplo.test/${que}","clic")`;
const inscribir = (sufijo, campos) => sql(`insert into registrations
    (activity_id, nombre, correo, telefono, es_mayor_edad, estado, token, created_at, updated_at)
    values (${actId}, ${lit(campos.nombre ?? `${MARCA} ${sufijo}`)}, ${lit(campos.correo ?? `${MARCA}-${sufijo}@prueba.test`)}, ${lit(campos.telefono ?? null)},
    1, 'pendiente', '${MARCA}-${sufijo}', now(), now()); select last_insert_id();`);

const CORREO_F = `=${MARCA}-formula@prueba.test`;
const ids = {
  con: inscribir('con', { telefono: CON }),
  sin: inscribir('sin', {}),
  formula: inscribir('formula', { nombre: F('nombre'), correo: CORREO_F, telefono: F('telefono') }),
};

try {
  // La respuesta de la evaluación de esa misma persona, con su texto libre.
  sql(`insert into activity_evaluations (activity_id, nombre, correo, experiencia, significado, motivacion, como_se_entero, ip_hash, created_at, updated_at)
      values (${actId}, '${MARCA}', ${lit(CORREO_F)}, 4, ${lit(F('significado'))}, 5, 'redes', repeat('0', 64), now(), now())`);
  // Y la actividad y su organización, renombradas un momento.
  sql(`update activities set titulo = ${lit(F('actividad'))} where id = ${actId};
      update organizations set nombre = ${lit(F('organizacion'))} where id = ${orgId}`);

  const comprobar = (cual, { status, filas, formulas }, columnas) => {
    const [cab, ...datos] = filas;
    const celda = (id, columna) => String(datos.find((f) => String(f[0]) === String(id))?.[cab.indexOf(columna)] ?? '«no está»');

    di(`${cual}: el archivo se descarga`, status === 200, status);
    di(`${cual}: «Teléfono» va detrás de «Correo»`, cab.indexOf('Teléfono') > 0 && cab[cab.indexOf('Teléfono') - 1] === 'Correo', cab.join(' | '));
    di(`${cual}: todas las filas tienen las mismas celdas que la cabecera`, datos.every((f) => f.length === cab.length));
    di(`${cual}: el teléfono informado sale tal cual`, celda(ids.con, 'Teléfono') === CON, celda(ids.con, 'Teléfono'));
    di(`${cual}: sin teléfono, la celda va vacía`, celda(ids.sin, 'Teléfono') === '', celda(ids.sin, 'Teléfono'));
    for (const [columna, esperado] of Object.entries(columnas)) {
      di(`${cual}: «${columna}» con = delante sale como texto`, celda(ids.formula, columna) === `'${esperado}`, celda(ids.formula, columna));
    }
    di(`${cual}: **ninguna celda va como fórmula**`, formulas === 0, `${formulas} fórmulas`);
  };

  const org = await entrar('/mi-cuenta/login', ORG, CLAVE_ORG);
  comprobar('Organizador', await bajar(`${BASE}/mi-cuenta/actividades/${actId}/participantes/exportar`, org), {
    Nombre: F('nombre'), Correo: CORREO_F, 'Teléfono': F('telefono'),
  });

  const admin = await entrar('/admin/login', ADMIN, CLAVE_ADMIN);
  const pregunta = JSON.parse(execFileSync(PHP, ['-r', `require 'vendor/autoload.php'; echo json_encode(App\\Models\\ActivityEvaluation::PREGUNTA_SIGNIFICADO);`], { encoding: 'utf8' }));
  comprobar('Panel', await bajar(`${BASE}/admin/inscripciones/exportar/descargar?actividad=${actId}&respuestas=1`, admin), {
    Nombre: F('nombre'), Correo: CORREO_F, 'Teléfono': F('telefono'),
    Actividad: F('actividad'), 'Organización': F('organizacion'), [pregunta]: F('significado'),
  });
} finally {
  sql(`update activities set titulo = ${lit(tituloAntes)} where id = ${actId};
      update organizations set nombre = ${lit(orgAntes)} where id = ${orgId};
      delete from activity_evaluations where nombre = '${MARCA}';
      delete from registrations where token like '${MARCA}-%'`);
}

const repuestos = sql(`select titulo from activities where id = ${actId}`) === tituloAntes
  && sql(`select nombre from organizations where id = ${orgId}`) === orgAntes;
di('la actividad y la organización quedaron como estaban', repuestos);

console.log(`\n${fallos ? `${fallos} MAL` : 'Todo bien'}`);
process.exit(fallos ? 1 : 0);
