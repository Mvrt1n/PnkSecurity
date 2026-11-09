'use strict';
const { query } = require('../config/db');

/** Busca un usuario por email (único). Devuelve el registro o null. */
async function buscarPorEmail(email) {
  const sql = `
    SELECT id, nombre, email, password, estado, totp_secret
      FROM usuarios
     WHERE email = $1`;
  const { rows } = await query(sql, [email]);
  return rows[0] || null;
}

/** Crea un usuario (la contraseña YA debe venir hasheada con bcrypt). */
async function crear({ nombre, email, passwordHash, totpSecret = null }) {
  const sql = `
    INSERT INTO usuarios (nombre, email, password, estado, totp_secret)
    VALUES ($1, $2, $3, '1', $4)
    RETURNING id, nombre, email`;
  const { rows } = await query(sql, [nombre, email, passwordHash, totpSecret]);
  return rows[0];
}

module.exports = { buscarPorEmail, crear };
