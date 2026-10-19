<?php
/**
 * Copie este archivo como config/config.local.php (NO versionar; está en .gitignore)
 * o defina las mismas claves como variables de entorno del servidor (recomendado en PROD).
 * Este directorio está FUERA del webroot (pnkSecurity/pnkSecurity) y no es accesible por HTTP.
 * ISO 27001 A.8.24 / ASC-13 / ASVS v5.0.0-13.3.1
 */
return [
    'APP_ENV'         => 'dev',   // 'prod' en producción (oculta errores)
    'APP_FORCE_HTTPS' => 'false', // 'true' si el sitio SIEMPRE va por HTTPS
    'TRUST_PROXY'     => 'false', // 'true' sólo si hay un proxy/balanceador TLS de confianza delante
    'DB_DSN'          => 'mysql:host=localhost;dbname=pnk_security;charset=utf8mb4',
    'DB_USER'         => 'pnk_app',                // nunca root
    'DB_PASS'         => '',                       // definir aquí o en variable de entorno DB_PASS
    'LOG_PATH'        => __DIR__ . '/../logs/security.log',
    'LOG_PEPPER'      => '',                       // valor aleatorio: php -r "echo bin2hex(random_bytes(32));"
];
