'use strict';
/**
 * Cabeceras y endurecimiento HTTP.
 * - helmet: CSP, nosniff, frameguard, HSTS (en prod/QA bajo TLS).
 * - forzar HTTPS cuando el ambiente lo exige (detrás de Cloudflare/ALB).
 */
const helmet = require('helmet');
const config = require('../config/env');

function helmetMiddleware() {
  return helmet({
    contentSecurityPolicy: {
      useDefaults: true,
      directives: {
        'default-src': ["'self'"],
        'img-src': ["'self'", 'data:'],
        'script-src': ["'self'"],
        // La plantilla usa estilos en línea heredados de la carta original
        'style-src': ["'self'", "'unsafe-inline'"],
        'font-src': ["'self'", 'data:'],
        'object-src': ["'none'"],
        'base-uri': ["'self'"],
        'frame-ancestors': ["'self'"],
        'upgrade-insecure-requests': config.app.forceHttps ? [] : null,
      },
    },
    hsts: config.app.forceHttps ? { maxAge: 31536000, includeSubDomains: true } : false,
    crossOriginEmbedderPolicy: false,
  });
}

/** Redirige a HTTPS si el ambiente lo exige y la petición llegó por HTTP. */
function forceHttps(req, res, next) {
  if (!config.app.forceHttps) return next();
  const proto = req.headers['x-forwarded-proto'] || req.protocol;
  if (proto !== 'https') {
    return res.redirect(301, `https://${req.headers.host}${req.originalUrl}`);
  }
  next();
}

module.exports = { helmetMiddleware, forceHttps };
