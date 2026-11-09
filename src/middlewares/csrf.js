'use strict';
/**
 * Protección CSRF por token sincronizado en sesión (sin dependencias extra).
 * - Genera un token por sesión y lo expone en res.locals.csrfToken para las vistas.
 * - Exige y verifica el token en métodos que cambian estado (POST/PUT/PATCH/DELETE).
 * - Comparación en tiempo constante (timingSafeEqual) para evitar fugas por tiempo.
 * Equivale al csrf_token()/exigir_post_csrf() del proyecto PHP original.
 */
const crypto = require('crypto');

function asegurarToken(req) {
  if (!req.session.csrfToken) {
    req.session.csrfToken = crypto.randomBytes(32).toString('hex');
  }
  return req.session.csrfToken;
}

/** Deja el token disponible en las vistas (<input name="_csrf">). */
function exposeToken(req, res, next) {
  res.locals.csrfToken = asegurarToken(req);
  next();
}

function tokensIguales(a, b) {
  if (typeof a !== 'string' || typeof b !== 'string') return false;
  const ba = Buffer.from(a);
  const bb = Buffer.from(b);
  if (ba.length !== bb.length) return false;
  return crypto.timingSafeEqual(ba, bb);
}

/** Verifica el token en peticiones que modifican estado. */
function verify(req, res, next) {
  const metodosSeguros = ['GET', 'HEAD', 'OPTIONS'];
  if (metodosSeguros.includes(req.method)) return next();

  const esperado = req.session && req.session.csrfToken;
  const recibido =
    (req.body && req.body._csrf) ||
    req.headers['x-csrf-token'] ||
    req.headers['x-xsrf-token'];

  if (!esperado || !tokensIguales(esperado, recibido)) {
    const err = new Error('Token CSRF inválido o ausente');
    err.status = 403;
    err.code = 'CSRF';
    return next(err);
  }
  next();
}

module.exports = { exposeToken, verify };
