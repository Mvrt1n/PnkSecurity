'use strict';
/**
 * Smoke test REAL contra un servidor en ejecución (APP_BASE_URL o localhost:PORT).
 * Verifica: /health, portada, carta válida, 404 de carta inexistente, y que
 * un POST sin CSRF sea rechazado (403). Sale 0 si todo pasa.
 */
const base = process.env.APP_BASE_URL || `http://127.0.0.1:${process.env.PORT || 3000}`;
let fallos = 0;

async function check(nombre, fn) {
  try { await fn(); console.log(`  ✓ ${nombre}`); }
  catch (e) { fallos++; console.log(`  ✗ ${nombre} -> ${e.message}`); }
}
function assert(cond, msg) { if (!cond) throw new Error(msg); }

(async () => {
  console.log(`Smoke test contra ${base}`);

  await check('GET /health = 200', async () => {
    const r = await fetch(`${base}/health`); assert(r.status === 200, `status ${r.status}`);
    const j = await r.json(); assert(j.status === 'ok', `db ${j.db}`);
  });

  await check('GET / (portada) = 200 HTML', async () => {
    const r = await fetch(`${base}/`); assert(r.status === 200, `status ${r.status}`);
  });

  await check('GET /carta/1 = 200 y contiene nombre del restaurante', async () => {
    const r = await fetch(`${base}/carta/1`); assert(r.status === 200, `status ${r.status}`);
    const html = await r.text(); assert(/Afríca Restobar|Restobar/.test(html), 'no aparece el restaurante');
  });

  await check('GET /carta/99999 = 404', async () => {
    const r = await fetch(`${base}/carta/99999`); assert(r.status === 404, `status ${r.status}`);
  });

  await check('POST /api/carrito/agregar SIN CSRF = 403', async () => {
    const r = await fetch(`${base}/api/carrito/agregar`, {
      method: 'POST', headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ itemId: 1 }),
    });
    assert(r.status === 403, `status ${r.status} (se esperaba 403 por CSRF)`);
  });

  console.log(fallos === 0 ? '\nSMOKE OK' : `\nSMOKE FALLÓ (${fallos})`);
  process.exit(fallos === 0 ? 0 : 1);
})();
