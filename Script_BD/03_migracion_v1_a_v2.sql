-- Migración de una BD existente (v1) a v2 sin perder datos. Ejecutar como administrador.
-- Después ejecutar: php herramientas/migrar_passwords.php  (convierte contraseñas en claro a bcrypt)
USE pnk_security;

ALTER DATABASE pnk_security CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;

ALTER TABLE comentarios
  ADD COLUMN usuario_id int(11) DEFAULT NULL AFTER usuario,
  ADD COLUMN creado datetime DEFAULT NULL,
  ADD KEY idx_com_resto (id_restaurante),
  ADD KEY idx_com_usuario (usuario_id);

-- Revisar y depurar comentarios existentes que contengan HTML/JS antes de la puesta en producción:
-- SELECT Id, usuario FROM comentarios WHERE comentario LIKE '%<%' OR usuario LIKE '%<%';

ALTER TABLE usuarios
  MODIFY email varchar(255) NOT NULL,
  MODIFY password varchar(255) NOT NULL,
  ADD COLUMN totp_secret varchar(64) DEFAULT NULL COMMENT 'secreto MFA/TOTP; NULL = sin 2FA',
  ADD UNIQUE KEY uq_usuarios_email (email);

CREATE TABLE IF NOT EXISTS login_intentos (
  id int(11) NOT NULL AUTO_INCREMENT,
  email_hash char(16) NOT NULL,
  ip varchar(45) NOT NULL,
  fecha datetime NOT NULL,
  PRIMARY KEY (id),
  KEY idx_li_email_fecha (email_hash, fecha),
  KEY idx_li_ip_fecha (ip, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Deshabilitar cuentas por defecto conocidas (ASVS v5.0.0-6.3.2)
UPDATE usuarios SET estado = '0' WHERE email IN ('admin@gmail.com');
