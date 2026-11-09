'use strict';
const express = require('express');
const rateLimit = require('express-rate-limit');
const { body, param } = require('express-validator');

const cartaController = require('../controllers/cartaController');
const comentarioController = require('../controllers/comentarioController');
const authController = require('../controllers/authController');
const carritoController = require('../controllers/carritoController');
const iaController = require('../controllers/iaController');

const { requireAuth } = require('../middlewares/auth');
const csrf = require('../middlewares/csrf');
const { handleValidation } = require('../middlewares/validate');
const { ping } = require('../config/db');

const router = express.Router();

// --- Limitadores de tasa (defensa ante abuso) -------------------------------
const loginLimiter = rateLimit({ windowMs: 15 * 60 * 1000, max: 20, standardHeaders: true, legacyHeaders: false });
const iaLimiter = rateLimit({ windowMs: 60 * 1000, max: 15, standardHeaders: true, legacyHeaders: false });
const writeLimiter = rateLimit({ windowMs: 60 * 1000, max: 30, standardHeaders: true, legacyHeaders: false });

// --- Salud (para Docker healthcheck / balanceadores) ------------------------
router.get('/health', async (req, res) => {
  try { await ping(); res.json({ status: 'ok', db: 'up' }); }
  catch { res.status(503).json({ status: 'degraded', db: 'down' }); }
});

// --- Vistas (View) ----------------------------------------------------------
router.get('/', cartaController.portada);
router.get('/carta/:id',
  param('id').isInt({ min: 1 }), handleValidation, cartaController.verCarta);
// Compatibilidad con el enlace del proyecto PHP: /?id=N  y  /index
router.get('/index', cartaController.verCarta);

router.get('/carrito', carritoController.ver);

// --- Autenticación ----------------------------------------------------------
router.post('/auth/login', loginLimiter,
  body('email').isEmail().normalizeEmail(),
  body('password').isString().isLength({ min: 1, max: 200 }),
  handleValidation, csrf.verify, authController.login);

router.post('/auth/logout', csrf.verify, authController.logout);

// --- Carrito (API JSON, carrito en sesión) ----------------------------------
router.get('/api/carrito', carritoController.obtener);
router.post('/api/carrito/agregar', writeLimiter, csrf.verify,
  body('itemId').isInt({ min: 1 }), handleValidation, carritoController.agregar);
router.post('/api/carrito/quitar', writeLimiter, csrf.verify,
  body('itemId').isInt({ min: 1 }), handleValidation, carritoController.quitar);
router.post('/api/carrito/vaciar', writeLimiter, csrf.verify, carritoController.vaciar);

// --- Comentarios (requieren sesión + CSRF; autor desde la sesión) -----------
router.post('/comentarios', writeLimiter, csrf.verify, requireAuth,
  body('comentario').isString().trim().isLength({ min: 1, max: 1000 }),
  body('restauranteId').optional().isInt({ min: 1 }),
  handleValidation, comentarioController.crear);

router.delete('/comentarios/:id', csrf.verify, requireAuth,
  param('id').isInt({ min: 1 }), handleValidation, comentarioController.eliminar);
// Variante POST para formularios HTML sin fetch
router.post('/comentarios/:id/eliminar', csrf.verify, requireAuth,
  param('id').isInt({ min: 1 }), handleValidation, comentarioController.eliminar);

// --- IA: asistente de la carta ----------------------------------------------
router.post('/api/ia/recomendar', iaLimiter, csrf.verify,
  body('pregunta').isString().trim().isLength({ min: 2, max: 300 }),
  body('restauranteId').optional().isInt({ min: 1 }),
  handleValidation, iaController.recomendar);

module.exports = router;
