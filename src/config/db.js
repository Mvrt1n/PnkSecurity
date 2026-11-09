'use strict';
/**
 * Pool de conexiones PostgreSQL (node-postgres).
 * - Credenciales SIEMPRE por variables de entorno (config/env.js).
 * - query(text, params): única vía de acceso; `params` fuerza el uso de
 *   placeholders $1,$2,... => previene SQL Injection (ASVS v5.0.0 V5).
 * - La app se conecta con un rol de MENOR PRIVILEGIO (solo DML).
 */
const { Pool } = require('pg');
const config = require('./env');

const pool = new Pool({
  host: config.db.host,
  port: config.db.port,
  database: config.db.database,
  user: config.db.user,
  password: config.db.password,
  max: config.db.max,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 5000,
  ssl: config.db.ssl ? { rejectUnauthorized: false } : false,
});

pool.on('error', (err) => {
  // Un cliente inactivo falló: registrar, no derribar el proceso.
  console.error(JSON.stringify({
    ts: new Date().toISOString(), level: 'error',
    evento: 'pg_pool_error', detalle: err.message,
  }));
});

/**
 * Ejecuta una consulta parametrizada.
 * @param {string} text  SQL con placeholders $1,$2,...
 * @param {Array}  params valores (nunca se concatenan en el SQL)
 */
async function query(text, params = []) {
  const start = Date.now();
  const res = await pool.query(text, params);
  if (config.debug) {
    console.debug(JSON.stringify({
      ts: new Date().toISOString(), level: 'debug', evento: 'sql',
      ms: Date.now() - start, filas: res.rowCount,
    }));
  }
  return res;
}

/** Comprueba conectividad real con la BD (para healthcheck / arranque). */
async function ping() {
  const res = await pool.query('SELECT 1 AS ok');
  return res.rows[0] && res.rows[0].ok === 1;
}

module.exports = { pool, query, ping };
