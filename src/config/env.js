'use strict';
/**
 * Carga y valida la configuración según el AMBIENTE activo (NODE_ENV).
 * Los tres ambientes (development / qa / production) usan archivos .env
 * independientes bajo /env, y en contenedor las variables llegan por el
 * entorno del proceso (docker-compose env_file / Secrets). Nunca hay
 * credenciales en el código.
 */
const path = require('path');
const fs = require('fs');
const dotenv = require('dotenv');

const NODE_ENV = (process.env.NODE_ENV || 'development').toLowerCase();

// Carga el archivo del ambiente si existe (en Docker las vars ya vienen del
// entorno, así que el archivo es opcional y nunca pisa lo ya definido).
const envFile = path.resolve(__dirname, '..', '..', 'env', `.env.${NODE_ENV}`);
if (fs.existsSync(envFile)) {
  dotenv.config({ path: envFile });
}
// .env genérico de respaldo (p. ej. para desarrollo local fuera de Docker)
const fallback = path.resolve(__dirname, '..', '..', '.env');
if (fs.existsSync(fallback)) {
  dotenv.config({ path: fallback });
}

function req(name, fallbackValue) {
  const v = process.env[name] ?? fallbackValue;
  if (v === undefined || v === '') {
    throw new Error(`Falta la variable de entorno obligatoria: ${name}`);
  }
  return v;
}

function bool(name, def = false) {
  const v = process.env[name];
  if (v === undefined) return def;
  return ['1', 'true', 'yes', 'on'].includes(String(v).toLowerCase());
}

const isProd = NODE_ENV === 'production';
const isQA = NODE_ENV === 'qa';
const isDev = NODE_ENV === 'development';

const config = {
  env: NODE_ENV,
  isProd,
  isQA,
  isDev,
  debug: bool('APP_DEBUG', isDev), // debug SOLO en desarrollo por defecto

  app: {
    port: parseInt(process.env.PORT || '3000', 10),
    // Detrás de Cloudflare/ALB confiamos en X-Forwarded-* (1 salto)
    trustProxy: bool('TRUST_PROXY', !isDev),
    forceHttps: bool('APP_FORCE_HTTPS', isProd),
    baseUrl: process.env.APP_BASE_URL || `http://localhost:${process.env.PORT || 3000}`,
  },

  db: {
    host: process.env.DB_HOST || 'localhost',
    port: parseInt(process.env.DB_PORT || '5432', 10),
    database: req('DB_NAME', 'pnk_carta'),
    user: req('DB_USER', 'pnk_app'),
    password: req('DB_PASSWORD', isDev ? 'pnk_app_dev' : undefined),
    // TLS a la BD en producción (RDS/entornos gestionados)
    ssl: bool('DB_SSL', false),
    max: parseInt(process.env.DB_POOL_MAX || '10', 10),
  },

  session: {
    // Secreto fuerte obligatorio fuera de desarrollo
    secret: req('SESSION_SECRET', isDev ? 'dev-session-secret-change-me' : undefined),
    name: process.env.SESSION_NAME || '__Host-pnk.sid',
    ttlMs: parseInt(process.env.SESSION_TTL_MS || String(1000 * 60 * 30), 10), // 30 min
  },

  security: {
    // pepper para seudonimizar correos en login_intentos (HMAC)
    logPepper: process.env.LOG_PEPPER || (isDev ? 'dev-log-pepper' : ''),
    bcryptRounds: parseInt(process.env.BCRYPT_ROUNDS || '12', 10),
    loginMaxPerAccount: parseInt(process.env.LOGIN_MAX_ACCOUNT || '5', 10),
    loginMaxPerIp: parseInt(process.env.LOGIN_MAX_IP || '30', 10),
    loginWindowMin: parseInt(process.env.LOGIN_WINDOW_MIN || '15', 10),
  },

  ia: {
    enabled: bool('IA_ENABLED', false),
    provider: process.env.IA_PROVIDER || 'anthropic',
    apiKey: process.env.IA_API_KEY || '', // SOLO backend; nunca al frontend
    model: process.env.IA_MODEL || 'claude-haiku-5-5',
    endpoint: process.env.IA_ENDPOINT || 'https://api.anthropic.com/v1/messages',
    timeoutMs: parseInt(process.env.IA_TIMEOUT_MS || '15000', 10),
  },
};

module.exports = config;
