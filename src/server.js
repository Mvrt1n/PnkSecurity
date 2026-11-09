'use strict';
const app = require('./app');
const config = require('./config/env');
const { ping, pool } = require('./config/db');
const { log } = require('./middlewares/errorHandler');

async function main() {
  // Verificación REAL de conectividad con PostgreSQL antes de aceptar tráfico
  try {
    await ping();
    log('info', 'db_ok', { host: config.db.host, db: config.db.database });
  } catch (e) {
    log('error', 'db_fail', { msg: e.message });
    // En producción no arrancamos sin BD; en dev avisamos y seguimos para
    // permitir levantar el servidor mientras se prepara la base.
    if (config.isProd) process.exit(1);
  }

  const server = app.listen(config.app.port, () => {
    log('info', 'server_up', {
      env: config.env, port: config.app.port, debug: config.debug, pid: process.pid,
    });
  });

  // Apagado ordenado (cierra conexiones) para contenedores/orquestadores
  const shutdown = (sig) => () => {
    log('info', 'shutdown', { sig });
    server.close(() => pool.end().then(() => process.exit(0)));
    setTimeout(() => process.exit(1), 10000).unref();
  };
  process.on('SIGTERM', shutdown('SIGTERM'));
  process.on('SIGINT', shutdown('SIGINT'));
}

main();
