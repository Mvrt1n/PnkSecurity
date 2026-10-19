<?php
/**
 * Autenticación de usuarios.
 * Corrige: SQLi / bypass de autenticación (PNK-01), contraseñas en claro (PNK-03),
 * fijación de sesión (PNK-08), fuerza bruta (PNK-09), cuentas inactivas (PNK-10).
 * ASVS v5.0.0: 1.2.4, 6.2.8, 6.3.1, 6.3.8, 7.2.4, 11.4.2, 16.3.1
 */
declare(strict_types=1);

require __DIR__ . '/setup.php';

iniciar_sesion_segura();
exigir_post_csrf();

const MAX_INTENTOS_CUENTA = 5;   // por cuenta (frena fuerza bruta dirigida)
const MAX_INTENTOS_IP     = 30;  // por IP: más alto para no bloquear a todos los clientes tras un NAT/Wi-Fi compartido
const VENTANA_BLOQUEO     = 900; // 15 minutos

$restaurante = entero_positivo($_SESSION['restaurante_id'] ?? null);
$destino     = '../index.php' . ($restaurante ? '?id=' . $restaurante : '');

$email    = texto_param($_POST, 'frmusuario', 255);
$password = $_POST['frmpassword'] ?? null;          // se verifica tal cual se recibe (6.2.8)
if (!is_string($password) || $password === '' || strlen($password) > 1024) {
    $password = null;
}

function fallo_login(string $motivo, ?string $email, string $destino): never
{
    if ($email !== null) {
        consulta('INSERT INTO login_intentos (email_hash, ip, fecha) VALUES (:e, :ip, :f)', [
            ':e' => seudonimo($email), ':ip' => $_SERVER['REMOTE_ADDR'] ?? '', ':f' => gmdate('Y-m-d H:i:s'),
        ]);
    }
    log_seguridad('login_fallido', ['motivo' => $motivo, 'email' => $email ? seudonimo($email) : null]);
    $_SESSION['flash'] = 'Usuario o contraseña incorrectos.'; // mensaje genérico, sin enumeración
    header('Location: ' . $destino);
    exit;
}

if ($email === null || $password === null) {
    fallo_login('datos_invalidos', null, $destino);
}

// Anti-automatización: intentos fallidos recientes por cuenta o por IP.
$desde = gmdate('Y-m-d H:i:s', time() - VENTANA_BLOQUEO);
$porCuenta = (int) consulta(
    'SELECT COUNT(*) FROM login_intentos WHERE fecha >= :d AND email_hash = :e',
    [':d' => $desde, ':e' => seudonimo($email)]
)->fetchColumn();
$porIp = (int) consulta(
    'SELECT COUNT(*) FROM login_intentos WHERE fecha >= :d AND ip = :ip',
    [':d' => $desde, ':ip' => $_SERVER['REMOTE_ADDR'] ?? '']
)->fetchColumn();

if ($porCuenta >= MAX_INTENTOS_CUENTA || $porIp >= MAX_INTENTOS_IP) {
    log_seguridad('login_bloqueado', ['email' => seudonimo($email), 'por_cuenta' => $porCuenta, 'por_ip' => $porIp]);
    $_SESSION['flash'] = 'Demasiados intentos. Espere 15 minutos e intente nuevamente.';
    header('Location: ' . $destino);
    exit;
}

$usuario = consulta(
    "SELECT Id, nombre, password, totp_secret FROM usuarios WHERE email = :email AND estado = '1'",
    [':email' => $email]
)->fetch();

// Hash ficticio para igualar el tiempo de respuesta cuando el usuario no existe (6.3.8).
$hashFicticio = '$2y$12$XjA3waewf97nFg5Qg2BJpeBIe6R3jhBrHPZbyg.v5vOwHr1uNuMK.';
$hash = is_array($usuario) ? (string) $usuario['password'] : $hashFicticio;

$valida = password_verify($password, $hash);   // se ejecuta siempre, exista o no el usuario
if (!is_array($usuario) || !$valida) {
    fallo_login('credenciales', $email, $destino);
}

// Segundo factor (MFA/TOTP): obligatorio sólo si la cuenta tiene un secreto configurado.
$totpSecret = (string) ($usuario['totp_secret'] ?? '');
if ($totpSecret !== '') {
    $codigo = texto_param($_POST, 'frmtotp', 10) ?? '';
    if ($codigo === '') {
        // La contraseña era correcta: se pide el código sin revelar si falló algo.
        $_SESSION['flash'] = 'Ingrese el código de verificación de su aplicación autenticadora.';
        header('Location: ' . $destino);
        exit;
    }
    if (!totp_verificar($totpSecret, $codigo)) {
        fallo_login('mfa', $email, $destino);
    }
}

// Re-hash transparente si cambian los parámetros del algoritmo.
if (password_needs_rehash($hash, PASSWORD_BCRYPT, HASH_OPCIONES)) {
    consulta('UPDATE usuarios SET password = :p WHERE Id = :id', [
        ':p' => password_hash($password, PASSWORD_BCRYPT, HASH_OPCIONES), ':id' => (int) $usuario['Id'],
    ]);
}

// Éxito: nueva sesión (anti fijación) y nuevo token CSRF.
session_regenerate_id(true);
$_SESSION['usuario_id']    = (int) $usuario['Id'];
$_SESSION['nombre']        = (string) $usuario['nombre'];
$_SESSION['auth_inicio']   = time();
$_SESSION['ultimo_acceso'] = time();
unset($_SESSION['csrf'], $_SESSION['flash']);

consulta('DELETE FROM login_intentos WHERE email_hash = :e', [':e' => seudonimo($email)]);
log_seguridad('login_exitoso', ['usuario_id' => (int) $usuario['Id']]);

header('Location: ' . $destino);
exit;
