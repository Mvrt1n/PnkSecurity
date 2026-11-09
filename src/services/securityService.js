'use strict';
const crypto = require('crypto');
const bcrypt = require('bcryptjs');
const config = require('../config/env');

/** Seudonimiza un correo para la tabla de intentos (no se guarda el correo). */
function emailHmac(email) {
  return crypto
    .createHmac('sha256', config.security.logPepper || 'pnk')
    .update(String(email).toLowerCase())
    .digest('hex')
    .slice(0, 16);
}

/** Hash bcrypt de una contraseña (coste configurable). */
async function hashPassword(plano) {
  return bcrypt.hash(plano, config.security.bcryptRounds);
}

/** Verificación en tiempo ~constante contra un hash bcrypt. */
async function verifyPassword(plano, hash) {
  try {
    return await bcrypt.compare(plano, hash);
  } catch {
    return false;
  }
}

module.exports = { emailHmac, hashPassword, verifyPassword };
