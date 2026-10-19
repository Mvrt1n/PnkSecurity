<?php
/**
 * Convierte contraseñas almacenadas en texto plano (v1) a hash bcrypt. Idempotente.
 * Uso: php herramientas/migrar_passwords.php
 * Recomendación: tras migrar, forzar cambio de contraseña a todos los usuarios,
 * porque las contraseñas en claro deben considerarse comprometidas.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../pnkSecurity/setup/setup.php';

$n = 0;
foreach (consulta('SELECT Id, password FROM usuarios')->fetchAll() as $u) {
    $info = password_get_info((string) $u['password']);
    if ($info['algo'] === null || $info['algo'] === 0) {   // no es un hash reconocido -> texto plano
        consulta('UPDATE usuarios SET password = :p WHERE Id = :id', [
            ':p' => password_hash((string) $u['password'], PASSWORD_BCRYPT, HASH_OPCIONES), ':id' => (int) $u['Id'],
        ]);
        $n++;
    }
}
log_seguridad('migracion_passwords', ['convertidas' => $n]);
echo "Contraseñas convertidas: $n\n";
