'use strict';
/**
 * Asistente de la Carta Digital.
 * Recomienda platos del PROPIO menú según lo que pide el comensal
 * (p. ej. "algo sin alcohol y económico", "un plato de carne destacado").
 *
 * Seguridad:
 *  - La API key vive SOLO en el backend (config.ia.apiKey). Nunca se envía
 *    al frontend; el navegador llama a /api/ia/recomendar, no al proveedor.
 *  - Si no hay key o IA_ENABLED=false, usa un motor LOCAL determinista
 *    (sin red), así la funcionalidad no queda rota en la demo.
 *  - Timeout con AbortController para no colgar la petición.
 */
const config = require('../config/env');

/** Motor local de respaldo: puntúa ítems por coincidencia de palabras y precio. */
function recomendacionLocal(pregunta, items) {
  const q = String(pregunta || '').toLowerCase();
  const palabras = q.split(/\W+/).filter((w) => w.length > 2);
  const barato = /(barat|económic|economic|menos|bajo)/.test(q);

  const puntuados = items.map((it) => {
    const texto = `${it.nombre} ${it.descripcion || ''} ${it.categoria || ''} ${it.carta || ''}`.toLowerCase();
    let score = 0;
    for (const w of palabras) if (texto.includes(w)) score += 2;
    if (barato && it.precio) score += Math.max(0, 3 - it.precio / 4000);
    return { it, score };
  });

  puntuados.sort((a, b) => b.score - a.score || (a.it.precio || 0) - (b.it.precio || 0));
  const top = puntuados.slice(0, 3).filter((p) => p.score > 0 || barato).map((p) => p.it);
  const elegidos = top.length ? top : items.slice(0, 3);

  const texto = elegidos.length
    ? 'Según el menú, te podrían gustar: ' +
      elegidos.map((i) => `${i.nombre} ($${i.precio})`).join(', ') + '.'
    : 'No encontré platos que coincidan con tu pedido en este menú.';

  return { fuente: 'local', texto, sugeridos: elegidos.map((i) => i.id) };
}

/** Llama al proveedor de IA (Anthropic Messages API por defecto). */
async function recomendacionIA(pregunta, items) {
  const lista = items
    .map((i) => `#${i.id} ${i.nombre} — ${i.categoria || ''} — $${i.precio} — ${i.descripcion || ''}`)
    .join('\n');

  const prompt =
    `Eres el asistente de una carta digital de restaurante. Recomienda como máximo 3 platos ` +
    `ÚNICAMENTE de la siguiente lista, según la petición del comensal. Responde breve y en español, ` +
    `nombrando los platos y su precio.\n\nMENÚ:\n${lista}\n\nPETICIÓN DEL COMENSAL: ${pregunta}`;

  const controller = new AbortController();
  const t = setTimeout(() => controller.abort(), config.ia.timeoutMs);
  try {
    const resp = await fetch(config.ia.endpoint, {
      method: 'POST',
      signal: controller.signal,
      headers: {
        'content-type': 'application/json',
        'x-api-key': config.ia.apiKey,
        'anthropic-version': '2023-06-01',
      },
      body: JSON.stringify({
        model: config.ia.model,
        max_tokens: 300,
        messages: [{ role: 'user', content: prompt }],
      }),
    });
    if (!resp.ok) throw new Error(`IA respondió ${resp.status}`);
    const data = await resp.json();
    const texto = (data.content && data.content[0] && data.content[0].text)
      ? data.content[0].text.trim()
      : 'Sin respuesta del asistente.';
    return { fuente: 'ia', texto, sugeridos: [] };
  } finally {
    clearTimeout(t);
  }
}

/**
 * Punto de entrada: decide IA real o motor local, y nunca lanza hacia el
 * usuario un error del proveedor (cae al modo local).
 */
async function recomendar(pregunta, items) {
  if (config.ia.enabled && config.ia.apiKey) {
    try {
      return await recomendacionIA(pregunta, items);
    } catch (e) {
      // Falla el proveedor -> degradación elegante al motor local
      return { ...recomendacionLocal(pregunta, items), degradado: true, motivo: e.message };
    }
  }
  return recomendacionLocal(pregunta, items);
}

module.exports = { recomendar };
