# Revisión de Requisitos de Seguridad — PNK Security (formato skill §25)

| Requisito funcional | Seguridad requerida | Riesgo | Control ISO 27001 | ASC | ASVS v5.0.0 | Criterio de aceptación |
|---|---|---|---|---|---|---|
| Ver la carta de un restaurante por QR (`index.php?id=`) | Validar `id` como entero; consultas parametrizadas; escapar toda la salida; 404 si no existe o está eliminado | SQLi, XSS reflejado, divulgación de datos | A.8.28, A.8.26 | ASC-10, ASC-14 | V1 (1.2.1, 1.2.4), V2 (2.2.1) | T09, T10, T11, T20 en PASA |
| Agregar y quitar productos del carrito | POST + CSRF; item visible y no eliminado; precio desde el servidor; límite de items | SQLi, CSRF, manipulación de precio, abuso | A.8.28 | ASC-14, ASC-29 | V2 (2.3.1, 2.3.2, 2.4.1), V3 (3.5.1), V4 (4.1.1) | T17, T18, T19 en PASA |
| Ver el carrito (`mostrar_carrito.php`) | Validar `keyid`; escapar los datos del carrito | XSS reflejado | A.8.28 | ASC-10 | V1 (1.2.1, 1.2.2) | T11 en PASA |
| Iniciar sesión | Contraseña con hash costoso; consulta parametrizada; bloqueo por intentos; mensaje genérico; regenerar sesión; sólo cuentas activas | Bypass, fuerza bruta, robo de credenciales, fijación | A.5.17, A.8.5, A.8.24 | ASC-07, ASC-09, ASC-12 | V6 (6.3.1, 6.3.2, 6.3.8), V7 (7.2.4), V11 (11.4.2) | T01, T02, T06, T07, T08 en PASA |
| Cerrar sesión | POST + CSRF; destruir la sesión y la cookie | CSRF de logout, sesión reutilizable | A.8.5 | ASC-09 | V3 (3.5.3), V7 (7.4.1) | T05 en PASA |
| Publicar un comentario | Autenticación en el servidor; autor desde la sesión; largo máximo; escape de salida; CSRF | Acceso no autorizado, suplantación, XSS almacenado | A.5.15, A.8.3, A.8.28 | ASC-08, ASC-10 | V8 (8.2.1, 8.2.3, 8.3.1), V1 (1.2.1) | T12, T13, T14 en PASA |
| Eliminar mi comentario | Verificar propiedad (`usuario_id`); CSRF | IDOR | A.8.3 | ASC-08, ASC-11 | V8 (8.2.2) | T15, T16 en PASA |
| Registrar actividad de seguridad | Eventos con quién, qué, cuándo (UTC) y dónde; sin datos sensibles; anti log-injection | No repudio, investigación de incidentes | A.8.15, A.8.16 | ASC-27 | V16 (16.2.1, 16.2.5, 16.3.1, 16.4.1) | T22 en PASA |
| Configuración y despliegue | Secretos fuera del código; sin root; debug apagado; sin listados de directorio | Escalamiento, divulgación | A.8.9, A.8.24, A.8.2 | ASC-06, ASC-13 | V13 (13.2.2, 13.2.3, 13.3.1, 13.4.2, 13.4.3) | T23, T24 en PASA + checklist del Gate 4 |
| Comunicación cliente-servidor | TLS 1.2+; HSTS; cookie Secure | Interceptación | A.8.20, A.8.24 | ASC-06, ASC-12 | V12 (12.1.1, 12.2.1), V3 (3.4.1, 3.3.1) | Captura de cabeceras en un ambiente con TLS (pendiente, EXC-01) |
| Tratar datos personales de usuarios | Aviso de privacidad, minimización, retención, supresión | Incumplimiento de la Ley 19.628 / readiness 21.719 | A.5.34, A.8.10 | ASC-03, ASC-11 | V14 (14.1.1, 14.2.7) | Aviso completado + T16 + purga programada |
| Librerías del frontend | Versiones sin CVE conocidos; inventario | Explotación de componentes | A.8.8, A.5.21 | ASC-15 | V15 (15.1.1, 15.1.2, 15.2.1) | SBOM vigente sin CVE altos abiertos |
