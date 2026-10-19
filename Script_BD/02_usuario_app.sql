-- Cuenta de BD de mínimo privilegio para la aplicación (ISO 27001 A.8.2 / ASVS v5.0.0-13.2.2, 13.2.3)
-- Ejecutar como administrador DESPUÉS de pnk_security.sql.
-- Reemplace la contraseña por un valor aleatorio largo y guárdelo SOLO en config/config.local.php
-- o en la variable de entorno DB_PASS. No versionar la contraseña real.

CREATE USER IF NOT EXISTS 'pnk_app'@'localhost' IDENTIFIED BY 'REEMPLAZAR_POR_CLAVE_ALEATORIA';

GRANT SELECT ON pnk_security.cartas       TO 'pnk_app'@'localhost';
GRANT SELECT ON pnk_security.categorias   TO 'pnk_app'@'localhost';
GRANT SELECT ON pnk_security.items        TO 'pnk_app'@'localhost';
GRANT SELECT ON pnk_security.restautantes TO 'pnk_app'@'localhost';
GRANT SELECT ON pnk_security.direcciones  TO 'pnk_app'@'localhost';
GRANT SELECT, UPDATE (password) ON pnk_security.usuarios TO 'pnk_app'@'localhost';
GRANT SELECT, INSERT, DELETE ON pnk_security.comentarios    TO 'pnk_app'@'localhost';
GRANT SELECT, INSERT, DELETE ON pnk_security.login_intentos TO 'pnk_app'@'localhost';
FLUSH PRIVILEGES;
