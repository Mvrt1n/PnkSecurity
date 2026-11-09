'use strict';
/** Exige sesión autenticada (control de acceso del lado servidor). */
function requireAuth(req, res, next) {
  if (req.session && req.session.usuario) return next();
  const err = new Error('Autenticación requerida');
  err.status = 401;
  err.code = 'no_autenticado';
  return next(err);
}

/** Deja el usuario de sesión disponible en las vistas. */
function exposeUser(req, res, next) {
  res.locals.usuario = (req.session && req.session.usuario) || null;
  next();
}

module.exports = { requireAuth, exposeUser };
