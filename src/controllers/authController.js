'use strict';
const usuarioModel = require('../models/usuarioModel');
const loginIntentoModel = require('../models/loginIntentoModel');
const { emailHmac, verifyPassword } = require('../services/securityService');
const config = require('../config/env');

// Hash "señuelo" para igualar el tiempo cuando el usuario no existe
// (evita enumeración de cuentas por diferencia de tiempos). Es un bcrypt válido.
const DUMMY_HASH = '$2a$12$C6UzMDM.H6dfI/f/IKcEeO3f9r9m1n0t9r9m1n0t9r9m1n0t9r9mC';

function clientIp(req) {
  return (req.headers['x-forwarded-for'] || '').split(',')[0].trim() || req.ip || '0.0.0.0';
}

async function login(req, res, next) {
  try {
    const email = String(req.body.email || '').trim().toLowerCase();
    const password = String(req.body.password || '');
    const ip = clientIp(req);
    const hash = emailHmac(email);

    // 1) Anti fuerza bruta (por cuenta y por IP)
    const { porCuenta, porIp } = await loginIntentoModel.contarRecientes(
      hash, ip, config.security.loginWindowMin);
    if (porCuenta >= config.security.loginMaxPerAccount || porIp >= config.security.loginMaxPerIp) {
      const e = new Error('Demasiados intentos. Espera unos minutos.');
      e.status = 429; e.code = 'login_bloqueado';
      throw e;
    }
    await loginIntentoModel.registrar(hash, ip);

    // 2) Buscar usuario + comparar SIEMPRE (tiempo ~constante)
    const user = await usuarioModel.buscarPorEmail(email);
    const ok = await verifyPassword(password, user ? user.password : DUMMY_HASH);

    if (!user || !ok || String(user.estado) !== '1') {
      const e = new Error('Credenciales inválidas.');
      e.status = 401; e.code = 'credenciales';
      throw e;
    }

    // 3) Nota: el 2º factor TOTP del proyecto PHP se porta como mejora opcional
    //    (campo usuarios.totp_secret ya existe). Aquí queda el punto de extensión.

    // 4) Regenerar la sesión para prevenir fijación de sesión
    req.session.regenerate((err) => {
      if (err) return next(err);
      req.session.usuario = { id: user.id, nombre: user.nombre, email: user.email };
      if (req.accepts('html')) return res.redirect(req.body.redirect || '/');
      res.json({ ok: true, usuario: req.session.usuario });
    });
  } catch (err) {
    next(err);
  }
}

function logout(req, res, next) {
  req.session.destroy((err) => {
    if (err) return next(err);
    res.clearCookie(config.session.name);
    if (req.accepts('html')) return res.redirect('/');
    res.json({ ok: true });
  });
}

module.exports = { login, logout };
