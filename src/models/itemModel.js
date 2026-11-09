'use strict';
const { query } = require('../config/db');

/** Devuelve un ítem visible y vigente (valida antes de meterlo al carrito). */
async function obtenerVisible(itemId) {
  const sql = `
    SELECT it.id, it.nombre, it.precio, it.foto, c.restautantes_id AS restaurante_id
      FROM items     it
      JOIN categorias ca ON ca.id = it.categorias_id
      JOIN cartas     c  ON c.id  = ca.cartas_id
     WHERE it.id = $1
       AND it.visible = true AND it.eliminado IS NULL
       AND ca.eliminado IS NULL AND c.eliminada IS NULL`;
  const { rows } = await query(sql, [itemId]);
  return rows[0] || null;
}

module.exports = { obtenerVisible };
