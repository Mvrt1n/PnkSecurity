'use strict';
const { query } = require('../config/db');

/**
 * Ítems destacados y visibles de un restaurante (para la portada de la carta).
 * Recorre items -> categorias -> cartas -> restaurante, todo parametrizado.
 */
async function destacados(restauranteId) {
  const sql = `
    SELECT it.id, it.nombre, it.descripcion, it.precio, it.tiempo,
           it.observaciones, it.puntuacion, it.foto
      FROM items     it
      JOIN categorias ca ON ca.id = it.categorias_id
      JOIN cartas     c  ON c.id  = ca.cartas_id
     WHERE c.restautantes_id = $1
       AND it.destacado = true
       AND it.visible   = true
       AND it.eliminado IS NULL
       AND ca.eliminado IS NULL
       AND c.eliminada  IS NULL
     ORDER BY it.puntuacion DESC, it.orden NULLS LAST, it.id`;
  const { rows } = await query(sql, [restauranteId]);
  return rows;
}

/**
 * Árbol completo del menú de un restaurante:
 *   cartas[] -> categorias[] -> items[]
 * Se obtiene con 3 consultas parametrizadas y se ensambla en memoria.
 */
async function arbolMenu(restauranteId) {
  const cartas = (await query(
    `SELECT id, nombre, orden, foto
       FROM cartas
      WHERE restautantes_id = $1 AND visible = true AND eliminada IS NULL
      ORDER BY orden NULLS LAST, id`, [restauranteId])).rows;

  if (cartas.length === 0) return [];

  const cartaIds = cartas.map((c) => c.id);

  const categorias = (await query(
    `SELECT id, nombre, orden, foto, cartas_id
       FROM categorias
      WHERE cartas_id = ANY($1::int[]) AND visible = true AND eliminado IS NULL
      ORDER BY orden NULLS LAST, id`, [cartaIds])).rows;

  const categoriaIds = categorias.map((c) => c.id);

  const items = categoriaIds.length === 0 ? [] : (await query(
    `SELECT id, nombre, descripcion, tiempo, observaciones, precio,
            destacado, puntuacion, foto, orden, categorias_id
       FROM items
      WHERE categorias_id = ANY($1::int[]) AND visible = true AND eliminado IS NULL
      ORDER BY orden NULLS LAST, id`, [categoriaIds])).rows;

  // Ensamblar el árbol
  const itemsPorCat = new Map();
  for (const it of items) {
    if (!itemsPorCat.has(it.categorias_id)) itemsPorCat.set(it.categorias_id, []);
    itemsPorCat.get(it.categorias_id).push(it);
  }
  const catsPorCarta = new Map();
  for (const ca of categorias) {
    ca.items = itemsPorCat.get(ca.id) || [];
    if (!catsPorCarta.has(ca.cartas_id)) catsPorCarta.set(ca.cartas_id, []);
    catsPorCarta.get(ca.cartas_id).push(ca);
  }
  for (const c of cartas) {
    c.categorias = catsPorCarta.get(c.id) || [];
  }
  return cartas;
}

/** Lista plana de ítems visibles (para el asistente de IA / búsquedas). */
async function itemsPlanos(restauranteId) {
  const sql = `
    SELECT it.id, it.nombre, it.descripcion, it.precio, it.observaciones,
           ca.nombre AS categoria, c.nombre AS carta
      FROM items     it
      JOIN categorias ca ON ca.id = it.categorias_id
      JOIN cartas     c  ON c.id  = ca.cartas_id
     WHERE c.restautantes_id = $1
       AND it.visible = true AND it.eliminado IS NULL
       AND ca.eliminado IS NULL AND c.eliminada IS NULL
     ORDER BY c.orden NULLS LAST, ca.orden NULLS LAST, it.orden NULLS LAST`;
  const { rows } = await query(sql, [restauranteId]);
  return rows;
}

module.exports = { destacados, arbolMenu, itemsPlanos };
