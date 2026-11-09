'use strict';
const { query } = require('../config/db');

/** Registra un intento de login (email seudonimizado con HMAC). */
async function registrar(emailHash, ip) {
  await query(
    `INSERT INTO login_intentos (email_hash, ip, fecha) VALUES ($1, $2, now())`,
    [emailHash, ip]
  );
}

/** Cuenta intentos recientes por cuenta y por IP dentro de la ventana. */
async function contarRecientes(emailHash, ip, ventanaMin) {
  const porCuenta = (await query(
    `SELECT count(*)::int AS n FROM login_intentos
      WHERE email_hash = $1 AND fecha > now() - ($2 || ' minutes')::interval`,
    [emailHash, String(ventanaMin)])).rows[0].n;

  const porIp = (await query(
    `SELECT count(*)::int AS n FROM login_intentos
      WHERE ip = $1 AND fecha > now() - ($2 || ' minutes')::interval`,
    [ip, String(ventanaMin)])).rows[0].n;

  return { porCuenta, porIp };
}

/** Limpia intentos antiguos (se puede llamar desde un cron/healthcheck). */
async function purgar(ventanaMin) {
  await query(
    `DELETE FROM login_intentos WHERE fecha < now() - ($1 || ' minutes')::interval`,
    [String(ventanaMin)]
  );
}

module.exports = { registrar, contarRecientes, purgar };
