'use strict';
const comentarioModel = require('../models/comentarioModel');

/** Crea un comentario. Requiere sesión (requireAuth en la ruta) + CSRF. */
async function crear(req, res, next) {
  try {
    const restauranteId = parseInt(req.body.restauranteId || req.session.restauranteId, 10);
    const texto = String(req.body.comentario || '').trim();

    if (!Number.isInteger(restauranteId) || restauranteId <= 0) {
      const e = new Error('Restaurante inválido'); e.status = 422; e.code = 'validacion';
      throw e;
    }
    // El autor se toma de la SESIÓN, nunca del formulario (anti-suplantación)
    const creado = await comentarioModel.crear({
      usuario: req.session.usuario.nombre || req.session.usuario.email,
      usuarioId: req.session.usuario.id,
      comentario: texto,
      restauranteId,
    });

    if (req.accepts('html')) return res.redirect(`/carta/${restauranteId}#comentarios`);
    res.status(201).json({ ok: true, comentario: creado });
  } catch (err) {
    next(err);
  }
}

/** Elimina un comentario SOLO si es del usuario autenticado (anti-IDOR). */
async function eliminar(req, res, next) {
  try {
    const comentarioId = parseInt(req.params.id, 10);
    const filas = await comentarioModel.eliminarPropio(comentarioId, req.session.usuario.id);
    if (filas === 0) {
      const e = new Error('No puedes eliminar este comentario'); e.status = 403; e.code = 'prohibido';
      throw e;
    }
    if (req.accepts('html')) return res.redirect('back');
    res.json({ ok: true });
  } catch (err) {
    next(err);
  }
}

module.exports = { crear, eliminar };
