'use strict';
const { query } = require('../config/db');

/** Comentarios de un restaurante (más recientes primero). */
async function listarPorRestaurante(restauranteId, limite = 50) {
  const sql = `
    SELECT id, usuario, usuario_id, comentario, creado
      FROM comentarios
     WHERE id_restaurante = $1
     ORDER BY creado DESC NULLS LAST, id DESC
     LIMIT $2`;
  const { rows } = await query(sql, [restauranteId, limite]);
  return rows;
}

/** Crea un comentario con autor autenticado (usuario_id de la sesión). */
async function crear({ usuario, usuarioId, comentario, restauranteId }) {
  const sql = `
    INSERT INTO comentarios (usuario, usuario_id, comentario, id_restaurante, creado)
    VALUES ($1, $2, $3, $4, now())
    RETURNING id, usuario, usuario_id, comentario, creado`;
  const { rows } = await query(sql, [usuario, usuarioId, comentario, restauranteId]);
  return rows[0];
}

/**
 * Elimina un comentario SOLO si pertenece al usuario autenticado
 * (control de propiedad anti-IDOR, ASVS v5.0.0 V8). Devuelve nº de filas.
 */
async function eliminarPropio(comentarioId, usuarioId) {
  const sql = `DELETE FROM comentarios WHERE id = $1 AND usuario_id = $2`;
  const { rowCount } = await query(sql, [comentarioId, usuarioId]);
  return rowCount;
}

module.exports = { listarPorRestaurante, crear, eliminarPropio };
