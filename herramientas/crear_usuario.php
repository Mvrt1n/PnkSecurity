<?php
/**
 * Alta de usuarios por consola (no expuesto por HTTP).
 * Uso: php herramientas/crear_usuario.php "Nombre" correo@ejemplo.cl
 * La contraseña se solicita por teclado (no queda en el historial del shell).
 * ASVS v5.0.0: 6.2.1, 6.2.4 (parcial), 6.2.5, 6.2.9, 11.4.2
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../pnkSecurity/setup/setup.php';

[$script, $nombre, $email] = array_pad($argv, 3, null);
if (!$nombre || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php crear_usuario.php \"Nombre\" correo@ejemplo.cl\n");
    exit(1);
}

fwrite(STDOUT, 'Contraseña (mín. 15 caracteres recomendado, mín. 8 obligatorio): ');
if (DIRECTORY_SEPARATOR === '/') { shell_exec('stty -echo'); }
$pass = rtrim((string) fgets(STDIN), "\r\n");
if (DIRECTORY_SEPARATOR === '/') { shell_exec('stty echo'); }
fwrite(STDOUT, "\n");

$comunes = ['12345678', 'password', 'contraseña', 'admin01', 'qwerty123', 'pnksecurity', 'inacap123'];
if (mb_strlen($pass) < 8 || mb_strlen($pass) > 128 || in_array(mb_strtolower($pass), $comunes, true)) {
    fwrite(STDERR, "Contraseña rechazada: largo 8-128 y no debe ser una contraseña común.\n");
    exit(1);
}

consulta("INSERT INTO usuarios (nombre, email, password, estado) VALUES (:n, :e, :p, '1')", [
    ':n' => $nombre, ':e' => $email, ':p' => password_hash($pass, PASSWORD_BCRYPT, HASH_OPCIONES),
]);
log_seguridad('usuario_creado', ['email' => seudonimo($email)]);
fwrite(STDOUT, "Usuario creado.\n");
