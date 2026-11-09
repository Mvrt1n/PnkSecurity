'use strict';
const menuModel = require('../models/menuModel');
const iaService = require('../services/iaService');

/**
 * Asistente de la Carta: recomienda platos del menú del restaurante activo.
 * POST /api/ia/recomendar { restauranteId, pregunta }
 * La API key de IA NUNCA llega aquí desde el cliente: vive en el backend.
 */
async function recomendar(req, res, next) {
  try {
    const restauranteId = parseInt(req.body.restauranteId || req.session.restauranteId, 10);
    const pregunta = String(req.body.pregunta || '').trim().slice(0, 300);

    if (!Number.isInteger(restauranteId) || restauranteId <= 0) {
      const e = new Error('Restaurante inválido'); e.status = 422; e.code = 'validacion'; throw e;
    }
    if (pregunta.length < 2) {
      const e = new Error('Escribe qué se te antoja'); e.status = 422; e.code = 'validacion'; throw e;
    }

    const items = await menuModel.itemsPlanos(restauranteId);
    if (items.length === 0) {
      return res.json({ ok: true, respuesta: { fuente: 'local', texto: 'Este menú aún no tiene platos.', sugeridos: [] } });
    }

    const respuesta = await iaService.recomendar(pregunta, items);
    res.json({ ok: true, respuesta });
  } catch (err) { next(err); }
}

module.exports = { recomendar };
