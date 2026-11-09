'use strict';
const itemModel = require('../models/itemModel');

/**
 * Carrito guardado en la SESIÓN del servidor (no en el cliente), así los
 * precios y la validez de los ítems no dependen de datos manipulables.
 * Estructura: req.session.carrito = { [itemId]: { id, nombre, precio, foto, cantidad } }
 */
function getCarrito(req) {
  if (!req.session.carrito) req.session.carrito = {};
  return req.session.carrito;
}

function resumen(carrito) {
  const items = Object.values(carrito);
  const total = items.reduce((s, i) => s + i.precio * i.cantidad, 0);
  const unidades = items.reduce((s, i) => s + i.cantidad, 0);
  return { items, total, unidades };
}

/** Agrega (o incrementa) un ítem validado contra la BD. */
async function agregar(req, res, next) {
  try {
    const itemId = parseInt(req.body.itemId, 10);
    if (!Number.isInteger(itemId) || itemId <= 0) {
      const e = new Error('Ítem inválido'); e.status = 422; e.code = 'validacion'; throw e;
    }
    const item = await itemModel.obtenerVisible(itemId);
    if (!item) {
      const e = new Error('El ítem no existe o no está disponible'); e.status = 404; e.code = 'no_encontrado'; throw e;
    }
    const carrito = getCarrito(req);
    if (carrito[itemId]) carrito[itemId].cantidad += 1;
    else carrito[itemId] = { id: item.id, nombre: item.nombre, precio: item.precio || 0, foto: item.foto, cantidad: 1 };

    res.json({ ok: true, carrito: resumen(carrito) });
  } catch (err) { next(err); }
}

/** Quita una unidad (o elimina) un ítem del carrito. */
function quitar(req, res, next) {
  try {
    const itemId = parseInt(req.body.itemId, 10);
    const carrito = getCarrito(req);
    if (carrito[itemId]) {
      carrito[itemId].cantidad -= 1;
      if (carrito[itemId].cantidad <= 0) delete carrito[itemId];
    }
    res.json({ ok: true, carrito: resumen(carrito) });
  } catch (err) { next(err); }
}

/** Vacía el carrito (NO cierra la sesión; corrige el bug del proyecto PHP). */
function vaciar(req, res, next) {
  try {
    req.session.carrito = {};
    res.json({ ok: true, carrito: resumen(req.session.carrito) });
  } catch (err) { next(err); }
}

/** JSON del carrito actual. */
function obtener(req, res) {
  res.json({ ok: true, carrito: resumen(getCarrito(req)) });
}

/** Vista del carrito (equivale a mostrar_carrito.php). */
function ver(req, res) {
  const carrito = resumen(getCarrito(req));
  res.render('carrito', { carrito, restauranteId: req.session.restauranteId || null });
}

module.exports = { agregar, quitar, vaciar, obtener, ver };
