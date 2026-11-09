'use strict';
/**
 * Manejo CENTRALIZADO de errores (requisito del profesor).
 * - Registra el error de forma estructurada (JSON a stdout -> apto para SIEM).
 * - En producción NO filtra detalles ni stack traces (ASVS v5.0.0 V16).
 * - Responde JSON o HTML según lo que acepte el cliente.
 */
const config = require('../config/env');

function log(nivel, evento, extra = {}) {
  process.stdout.write(JSON.stringify({
    ts: new Date().toISOString(), level: nivel, evento, env: config.env, ...extra,
  }) + '\n');
}

/** 404: ruta no encontrada. */
function notFound(req, res, next) {
  res.status(404);
  if (req.accepts('html')) {
    return res.render('error', { titulo: 'No encontrado', codigo: 404,
      mensaje: 'La página solicitada no existe.' });
  }
  res.json({ error: 'no_encontrado' });
}

/** Manejador final de errores. */
// eslint-disable-next-line no-unused-vars
function errorHandler(err, req, res, next) {
  const status = err.status || 500;

  log(status >= 500 ? 'error' : 'warn', 'http_error', {
    status, code: err.code || null, ruta: req.originalUrl, metodo: req.method,
    msg: err.message,
    stack: config.debug ? err.stack : undefined, // stack solo en debug/dev
  });

  res.status(status);
  const mensajePublico = status >= 500 && !config.debug
    ? 'Ocurrió un error interno. Intenta nuevamente.'
    : err.message;

  if (req.accepts('html')) {
    return res.render('error', { titulo: 'Error', codigo: status, mensaje: mensajePublico });
  }
  res.json({ error: err.code || 'error', mensaje: mensajePublico });
}

module.exports = { notFound, errorHandler, log };
