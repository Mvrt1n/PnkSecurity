'use strict';
/**
 * Crea un usuario con contraseña hasheada (bcrypt). No hay cuentas por defecto.
 * Uso:
 *   node scripts/crear_usuario.js "Nombre Apellido" correo@ejemplo.cl "ContraseñaSegura"
 * La contraseña puede pasarse por argumento o por la variable USER_PASSWORD.
 */
const usuarioModel = require('../src/models/usuarioModel');
const { hashPassword } = require('../src/services/securityService');
const { pool } = require('../src/config/db');

async function main() {
  const [nombre, email] = process.argv.slice(2);
  const password = process.argv[4] || process.env.USER_PASSWORD;
  if (!nombre || !email || !password) {
    console.error('Uso: node scripts/crear_usuario.js "Nombre" correo@ejemplo.cl "Contraseña"');
    process.exit(2);
  }
  const hash = await hashPassword(password);
  const u = await usuarioModel.crear({ nombre, email: email.toLowerCase(), passwordHash: hash });
  console.log('Usuario creado:', u);
  await pool.end();
}
main().catch((e) => { console.error('Error:', e.message); process.exit(1); });
