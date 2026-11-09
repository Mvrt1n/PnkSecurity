'use strict';
const { query } = require('../config/db');

/** Devuelve un restaurante vigente (no eliminado) con su dirección, o null. */
async function obtenerPorId(id) {
  const sql = `
    SELECT r.id, r.nombre, r.fono, r.email, r.foto, r.mapa,
           d.calle, d.numero, d.comuna, d.region
      FROM restautantes r
      JOIN direcciones  d ON d.id = r.direcciones_id
     WHERE r.id = $1
       AND r.eliminado IS NULL`;
  const { rows } = await query(sql, [id]);
  return rows[0] || null;
}

/** Lista de restaurantes vigentes (para una portada / selector). */
async function listar() {
  const sql = `
    SELECT id, nombre, foto
      FROM restautantes
     WHERE eliminado IS NULL
     ORDER BY nombre`;
  const { rows } = await query(sql);
  return rows;
}

module.exports = { obtenerPorId, listar };
