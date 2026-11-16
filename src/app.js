'use strict';
const path = require('path');
const express = require('express');
const session = require('express-session');
const PgSession = require('connect-pg-simple')(session);
const morgan = require('morgan');
const compression = require('compression');

const config = require('./config/env');
const { pool } = require('./config/db');
const { helmetMiddleware, forceHttps } = require('./middlewares/security');
const csrf = require('./middlewares/csrf');
const { exposeUser } = require('./middlewares/auth');
const { notFound, errorHandler } = require('./middlewares/errorHandler');
const routes = require('./routes');

const app = express();

// Detrás de Cloudflare / ALB: confiar en 1 salto de proxy para IP y protocolo
app.set('trust proxy', config.app.trustProxy ? 1 : false);

// Vistas (View del MVC) — EJS
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

// Seguridad de cabeceras + HTTPS obligado por ambiente
app.use(helmetMiddleware());
app.use(forceHttps);

// Logs de acceso (estructurados fuera de desarrollo)
app.use(morgan(config.isDev ? 'dev' : 'combined'));

app.use(compression());
app.use(express.urlencoded({ extended: false, limit: '64kb' }));
app.use(express.json({ limit: '64kb' }));

// Estáticos (css/js/img reutilizados de la carta original)
app.use('/public', express.static(path.join(__dirname, '..', 'public'), {
  maxAge: config.isProd ? '7d' : 0,
}));

// Sesión persistida en PostgreSQL (misma BD). La tabla "session" se crea en el
// esquema (db/init/01_schema.sql), NO en caliente: createTableIfMissing:false
// evita la carrera de creación concurrente entre varias instancias del ASG.
const cookieSegura = config.app.forceHttps;
app.use(session({
  store: new PgSession({ pool, tableName: 'session', createTableIfMissing: false }),
  name: cookieSegura ? '__Host-pnk.sid' : 'pnk.sid',
  secret: config.session.secret,
  resave: false,
  saveUninitialized: false,
  rolling: true,
  cookie: {
    httpOnly: true,
    sameSite: 'lax',
    secure: cookieSegura,     // solo por HTTPS en QA/prod
    maxAge: config.session.ttlMs,
    path: '/',
  },
}));

// Exponer usuario y token CSRF a las vistas
app.use(exposeUser);
app.use(csrf.exposeToken);

// Rutas de la aplicación
app.use('/', routes);

// 404 + manejo centralizado de errores (siempre al final)
app.use(notFound);
app.use(errorHandler);

module.exports = app;
