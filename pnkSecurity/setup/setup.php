<?php
/**
 * PNK Security — núcleo de seguridad compartido.
 *
 * Controles implementados (ver docs/04_Catalogo_ASC.md):
 *  - ASC-13 Gestión de secretos: credenciales de BD fuera del código (env / config fuera del webroot).
 *  - ASC-10 / ASC-14: PDO con consultas preparadas reales, escape de salida contextual.
 *  - ASC-09 Sesiones: cookie HttpOnly/SameSite/Secure, regeneración, expiración.
 *  - ASC-08 / CSRF: token sincronizado por sesión.
 *  - ASC-27 / ASC-28: logging de seguridad y manejo genérico de errores.
 *  - ASC-06: cabeceras de seguridad HTTP.
 */

declare(strict_types=1);

if (defined('PNK_SETUP_LOADED')) {
    return;
}
define('PNK_SETUP_LOADED', true);

/* ------------------------------------------------------------------ *
 * Configuración (ASVS v5.0.0-13.3.1, ISO 27001 A.8.9 / A.8.24)
 * ------------------------------------------------------------------ */

function config(string $clave, $defecto = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = [];
        // Archivo opcional FUERA del directorio público (no versionado).
        $archivo = getenv('PNK_CONFIG_FILE') ?: dirname(__DIR__, 2) . '/config/config.local.php';
        if (is_file($archivo)) {
            $leido = require $archivo;
            if (is_array($leido)) {
                $cfg = $leido;
            }
        }
    }
    $env = getenv($clave);
    if ($env !== false && $env !== '') {
        return $env;
    }
    return $cfg[$clave] ?? $defecto;
}

function es_produccion(): bool
{
    return strtolower((string) config('APP_ENV', 'prod')) === 'prod';
}

/* ------------------------------------------------------------------ *
 * Manejo de errores (ASVS v5.0.0-16.5.1, 16.5.4, 13.4.2)
 * ------------------------------------------------------------------ */

ini_set('display_errors', es_produccion() ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e): void {
    log_seguridad('error_no_controlado', [
        'tipo' => get_class($e),
        'mensaje' => $e->getMessage(),
        'archivo' => basename($e->getFile()) . ':' . $e->getLine(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html lang="es"><meta charset="UTF-8"><title>Error</title>'
       . '<p>Ocurrió un error inesperado. Intente nuevamente más tarde.</p></html>';
    exit;
});

/* ------------------------------------------------------------------ *
 * Base de datos (ASVS v5.0.0-1.2.4, 13.2.2, 13.2.3)
 * ------------------------------------------------------------------ */

function conectar(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn  = (string) config('DB_DSN', 'mysql:host=localhost;dbname=pnk_security;charset=utf8mb4');
    $user = config('DB_USER');
    $pass = config('DB_PASS');

    if (str_starts_with($dsn, 'mysql:') && (in_array($user, [null, '', 'root'], true) || $pass === null || $pass === '')) {
        // Se rechaza la cuenta root o una contraseña vacía (credencial por defecto).
        throw new RuntimeException('Configuración de BD insegura: defina DB_USER (no root) y DB_PASS.');
    }

    $pdo = new PDO($dsn, $user ?: null, $pass ?: null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // parametrización real en el servidor
    ]);
    return $pdo;
}

/** Ejecuta una consulta preparada y devuelve el statement. */
function consulta(string $sql, array $params = []): PDOStatement
{
    $stmt = conectar()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Parámetros de hashing de contraseñas (ASVS v5.0.0-11.4.2): bcrypt coste 12. */
const HASH_OPCIONES = ['cost' => 12];

/* ------------------------------------------------------------------ *
 * Validación de entradas (ASVS v5.0.0-2.2.1, 2.2.2, 15.3.5)
 * ------------------------------------------------------------------ */

/** Devuelve un entero positivo o null. Lista blanca: sólo dígitos. */
function entero_positivo($valor): ?int
{
    if (is_int($valor)) {
        return $valor > 0 ? $valor : null;
    }
    if (!is_string($valor) || !preg_match('/^[1-9][0-9]{0,9}$/', $valor)) {
        return null;
    }
    $n = (int) $valor;
    return $n > 0 && $n <= 2147483647 ? $n : null;
}

function texto_param(array $origen, string $clave, int $max): ?string
{
    $v = $origen[$clave] ?? null;
    if (!is_string($v)) {
        return null;
    }
    $v = trim($v);
    if ($v === '' || !mb_check_encoding($v, 'UTF-8') || mb_strlen($v) > $max) {
        return null;
    }
    return $v;
}

/* ------------------------------------------------------------------ *
 * Codificación de salida (ASVS v5.0.0-1.2.1, 1.2.2, 3.2.2)
 * ------------------------------------------------------------------ */

/** Escape HTML para contenido y atributos entre comillas. */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Nombre de archivo seguro para construir rutas de imágenes (sin rutas ni esquemas). */
function archivo_seguro(?string $nombre): ?string
{
    if ($nombre === null || $nombre === '') {
        return null;
    }
    return preg_match('/^[A-Za-z0-9_\-]{1,150}\.(jpe?g|png|gif|webp)$/i', $nombre) ? $nombre : null;
}

/* ------------------------------------------------------------------ *
 * Cabeceras de seguridad (ASVS v5.0.0-3.4.1, 3.4.3-3.4.6, 3.4.8, 14.3.2)
 * ------------------------------------------------------------------ */

function es_https(): bool
{
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')) {
        return true;
    }
    // Forzado por configuración (p. ej. sitio siempre detrás de TLS).
    if (filter_var(config('APP_FORCE_HTTPS', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        return true;
    }
    // Detrás de un proxy/balanceador de confianza (nginx, Apache, ELB): sólo se
    // confía en X-Forwarded-Proto cuando se declara TRUST_PROXY=true, para que un
    // cliente no pueda falsear la cabecera (ASVS v5.0.0-15.3.4).
    if (filter_var(config('TRUST_PROXY', 'false'), FILTER_VALIDATE_BOOLEAN)
        && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return false;
}

function cabeceras_seguridad(bool $sinCache = true): void
{
    if (headers_sent()) {
        return;
    }
    header_remove('X-Powered-By');
    header('Content-Type: text/html; charset=UTF-8');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; "
        . "form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Frame-Options: DENY');
    if (es_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    if ($sinCache) {
        header('Cache-Control: no-store');
    }
}

/* ------------------------------------------------------------------ *
 * Sesiones (ASVS v5.0.0-3.3.1, 3.3.2, 3.3.4, 7.2.4, 7.3.1, 7.3.2, 7.4.1)
 * ------------------------------------------------------------------ */

const SESION_INACTIVIDAD = 1800;   // 30 min
const SESION_MAXIMA      = 28800;  // 8 h

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = es_https() || filter_var(config('APP_FORCE_HTTPS', 'false'), FILTER_VALIDATE_BOOLEAN);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    // ID de sesión por defecto de PHP: 32 caracteres hex desde CSPRNG = 128 bits (ASVS 7.2.3).

    // Prefijo __Host- sólo es válido con Secure; en DEV sin TLS se usa un nombre sin prefijo.
    session_name($https ? '__Host-PNKSESSID' : 'PNKSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $ahora = time();
    if (isset($_SESSION['usuario_id'])) {
        $inicio = (int) ($_SESSION['auth_inicio'] ?? 0);
        $ultimo = (int) ($_SESSION['ultimo_acceso'] ?? 0);
        if ($ahora - $ultimo > SESION_INACTIVIDAD || $ahora - $inicio > SESION_MAXIMA) {
            log_seguridad('sesion_expirada', ['usuario_id' => $_SESSION['usuario_id']]);
            cerrar_sesion_usuario();
            session_start();
        }
    }
    $_SESSION['ultimo_acceso'] = $ahora;
}

function usuario_autenticado(): bool
{
    return isset($_SESSION['usuario_id']) && is_int($_SESSION['usuario_id']);
}

/** Destruye completamente la sesión del lado servidor y la cookie. */
function cerrar_sesion_usuario(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'],
            'httponly' => true, 'samesite' => $p['samesite'] ?: 'Lax',
        ]);
        session_destroy();
    }
}

/* ------------------------------------------------------------------ *
 * CSRF (ASVS v5.0.0-3.5.1, 3.5.3)
 * ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Exige método POST y token CSRF válido; en caso contrario responde 403/405 y termina. */
function exigir_post_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Método no permitido');
    }
    $enviado = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($enviado) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $enviado)) {
        log_seguridad('csrf_invalido', ['ruta' => $_SERVER['SCRIPT_NAME'] ?? '']);
        http_response_code(403);
        exit('Solicitud inválida');
    }
}

/* ------------------------------------------------------------------ *
 * Logging de seguridad (ASVS v5.0.0-16.2.1, 16.2.2, 16.2.5, 16.3.x, 16.4.1)
 * ------------------------------------------------------------------ */

/** Seudonimiza un identificador (p. ej. email) para no registrarlo en claro. */
function seudonimo(string $valor): string
{
    $sal = (string) config('LOG_PEPPER', 'pnk-log');
    return substr(hash_hmac('sha256', mb_strtolower(trim($valor)), $sal), 0, 16);
}

function log_seguridad(string $evento, array $datos = []): void
{
    $ruta = (string) config('LOG_PATH', dirname(__DIR__, 2) . '/logs/security.log');
    $registro = [
        'ts'      => gmdate('Y-m-d\TH:i:s\Z'),           // UTC
        'evento'  => $evento,
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'cli',
        'ruta'    => $_SERVER['SCRIPT_NAME'] ?? '',
        'usuario' => $_SESSION['usuario_id'] ?? null,
        'datos'   => $datos,
    ];
    // json_encode escapa saltos de línea y caracteres de control -> evita log injection.
    $linea = json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
    $dir = dirname($ruta);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (@file_put_contents($ruta, $linea, FILE_APPEND | LOCK_EX) === false) {
        error_log('[pnk-security] ' . $linea);
    }
}

/* ------------------------------------------------------------------ *
 * Autenticación multifactor — TOTP (RFC 6238 / RFC 4226)
 * ISO 27001 A.8.5 · ASC-07 · ASVS v5.0.0-6.3.3
 *
 * Segundo factor OPCIONAL: un usuario sin `totp_secret` inicia sesión con
 * sólo contraseña (compatible con lo anterior). Si tiene secreto, el login
 * exige además un código de 6 dígitos de una app autenticadora (Google
 * Authenticator, Aegis, FreeOTP…). Implementación sin dependencias externas.
 * ------------------------------------------------------------------ */

/** Genera un secreto base32 (sin padding) para una cuenta TOTP. */
function totp_generar_secreto(int $bytes = 20): string
{
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $aleatorio = random_bytes($bytes);
    $bits = '';
    foreach (str_split($aleatorio) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $salida = '';
    foreach (str_split($bits, 5) as $trozo) {
        $salida .= $alfabeto[bindec(str_pad($trozo, 5, '0'))];
    }
    return $salida;
}

/** Decodifica base32 (RFC 4648) a binario. Devuelve '' si el secreto es inválido. */
function base32_decode(string $b32): string
{
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(rtrim($b32, '='));
    if ($b32 === '' || strspn($b32, $alfabeto) !== strlen($b32)) {
        return '';
    }
    $bits = '';
    foreach (str_split($b32) as $c) {
        $bits .= str_pad(decbin(strpos($alfabeto, $c)), 5, '0', STR_PAD_LEFT);
    }
    $binario = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $binario .= chr(bindec($byte));
        }
    }
    return $binario;
}

/** Calcula el código TOTP de 6 dígitos para un contador de tiempo dado. */
function totp_codigo(string $secretoBin, int $contador): string
{
    $bin = pack('N*', 0) . pack('N*', $contador);     // contador de 64 bits big-endian
    $hash = hash_hmac('sha1', $bin, $secretoBin, true);
    $offset = ord($hash[19]) & 0x0f;
    $parte = (ord($hash[$offset]) & 0x7f) << 24
           | (ord($hash[$offset + 1]) & 0xff) << 16
           | (ord($hash[$offset + 2]) & 0xff) << 8
           | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string) ($parte % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Verifica un código TOTP de 6 dígitos, tolerando ±1 ventana (±30 s) por
 * desfase de reloj. Comparación en tiempo constante.
 */
function totp_verificar(string $secretoB32, string $codigo, int $periodo = 30): bool
{
    $codigo = preg_replace('/\D/', '', $codigo);
    if (strlen($codigo) !== 6) {
        return false;
    }
    $bin = base32_decode($secretoB32);
    if ($bin === '') {
        return false;
    }
    $ventana = (int) floor(time() / $periodo);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totp_codigo($bin, $ventana + $i), $codigo)) {
            return true;
        }
    }
    return false;
}

/** URI otpauth:// para generar el código QR en una app autenticadora. */
function totp_uri(string $secretoB32, string $cuenta, string $emisor = 'PNK Security'): string
{
    return 'otpauth://totp/' . rawurlencode($emisor . ':' . $cuenta)
         . '?secret=' . $secretoB32
         . '&issuer=' . rawurlencode($emisor)
         . '&algorithm=SHA1&digits=6&period=30';
}

/* ------------------------------------------------------------------ *
 * Utilidades de presentación (funcionales, sin cambios de negocio)
 * ------------------------------------------------------------------ */

function quitarespacios(string $titulo): string
{
    return str_replace([' ', 'ñ', 'Ñ'], '', $titulo);
}

function moneda_chilena($numero): string
{
    return '$ ' . number_format((int) $numero, 0, ',', '.');
}
