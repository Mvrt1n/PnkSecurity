'use strict';
/** Recolecta los errores de express-validator y corta con 422 si hay. */
const { validationResult } = require('express-validator');

function handleValidation(req, res, next) {
  const result = validationResult(req);
  if (result.isEmpty()) return next();
  const err = new Error('Datos de entrada inválidos');
  err.status = 422;
  err.code = 'validacion';
  err.detalles = result.array().map((e) => ({ campo: e.path, msg: e.msg }));
  return next(err);
}

module.exports = { handleValidation };
