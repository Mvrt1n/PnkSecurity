'use strict';
const restauranteModel = require('../models/restauranteModel');
const menuModel = require('../models/menuModel');
const comentarioModel = require('../models/comentarioModel');

/**
 * Portada de la Carta Digital de un restaurante.
 * Equivale a index.php?id=N del proyecto PHP. El id se valida como entero
 * positivo en la ruta (express-validator); aquí ya llega saneado.
 */
async function verCarta(req, res, next) {
  try {
    const id = parseInt(req.params.id || req.query.id, 10);
    if (!Number.isInteger(id) || id <= 0) {
      return res.status(404).render('error', {
        titulo: 'Restaurante no encontrado', codigo: 404,
        mensaje: 'Restaurante no encontrado.',
      });
    }

    const restaurante = await restauranteModel.obtenerPorId(id);
    if (!restaurante) {
      return res.status(404).render('error', {
        titulo: 'Restaurante no encontrado', codigo: 404,
        mensaje: 'Restaurante no encontrado.',
      });
    }

    const [menu, destacados, comentarios] = await Promise.all([
      menuModel.arbolMenu(id),
      menuModel.destacados(id),
      comentarioModel.listarPorRestaurante(id),
    ]);

    // Recordar el restaurante activo para el carrito/comentarios
    req.session.restauranteId = id;

    res.render('carta', { restaurante, menu, destacados, comentarios });
  } catch (err) {
    next(err);
  }
}

/** Portada: lista de restaurantes disponibles. */
async function portada(req, res, next) {
  try {
    const restaurantes = await restauranteModel.listar();
    res.render('portada', { restaurantes });
  } catch (err) {
    next(err);
  }
}

module.exports = { verCarta, portada };
