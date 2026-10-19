<?php
/**
 * Activa o desactiva el segundo factor (MFA/TOTP) de un usuario. Sólo CLI.
 *   Activar:     php herramientas/activar_mfa.php correo@ejemplo.cl
 *   Desactivar:  php herramientas/activar_mfa.php correo@ejemplo.cl --off
 * Al activar, imprime el secreto y la URI otpauth:// para cargarla en
 * Google Authenticator / Aegis / FreeOTP (como texto o código QR).
 * ASVS v5.0.0-6.3.3 · ISO 27001 A.8.5 · ASC-07
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../pnkSecurity/setup/setup.php';

$email = $argv[1] ?? null;
$apagar = in_array('--off', $argv, true);
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php activar_mfa.php correo@ejemplo.cl [--off]\n");
    exit(1);
}

$u = consulta('SELECT Id FROM usuarios WHERE email = :e', [':e' => $email])->fetch();
if (!$u) { fwrite(STDERR, "No existe un usuario con ese correo.\n"); exit(1); }

if ($apagar) {
    consulta('UPDATE usuarios SET totp_secret = NULL WHERE Id = :id', [':id' => (int) $u['Id']]);
    log_seguridad('mfa_desactivada', ['usuario_id' => (int) $u['Id']]);
    echo "MFA desactivada para $email.\n";
    exit(0);
}

$secreto = totp_generar_secreto();
consulta('UPDATE usuarios SET totp_secret = :s WHERE Id = :id', [':s' => $secreto, ':id' => (int) $u['Id']]);
log_seguridad('mfa_activada', ['usuario_id' => (int) $u['Id']]);
echo "MFA activada para $email.\n";
echo "Secreto (base32): $secreto\n";
echo "URI otpauth:      " . totp_uri($secreto, $email) . "\n";
echo "Cargue esa URI en su app autenticadora (como QR o texto) y pruebe el código en el login.\n";
