<?php
/**
 * Pruebas automatizadas de controles de seguridad (evidencia ASC-20 / ISO 27001 A.8.29).
 *
 * Ejecución LOCAL y aislada: levanta el servidor embebido de PHP sobre 127.0.0.1 con una
 * base SQLite temporal y datos sintéticos. No interactúa con ningún sistema externo.
 *
 * Uso:  php tests/pruebas_seguridad.php
 * Salida: resumen en consola y tests/resultados_pruebas.txt
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$raiz    = dirname(__DIR__);
$webroot = $raiz . '/pnkSecurity';
$tmp     = sys_get_temp_dir() . '/pnk_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
$db      = $tmp . '/test.db';
$log     = $tmp . '/security.log';

// ---------- Base de datos sintética ----------
$pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(file_get_contents(__DIR__ . '/fixture_sqlite.sql'));
$ins = $pdo->prepare('INSERT INTO usuarios (nombre, email, password, estado) VALUES (?,?,?,?)');
$ins->execute(['Ana Prueba',   'ana@example.com',      password_hash('Clave-Larga-De-Prueba-1', PASSWORD_BCRYPT, ['cost' => 10]), '1']);
$ins->execute(['Beto Prueba',  'beto@example.com',     password_hash('Clave-Larga-De-Prueba-2', PASSWORD_BCRYPT, ['cost' => 10]), '1']);
$ins->execute(['Inactivo',     'inactivo@example.com', password_hash('Clave-Larga-De-Prueba-3', PASSWORD_BCRYPT, ['cost' => 10]), '0']);
$ins->execute(['Bruto',        'bruto@example.com',    password_hash('Clave-Larga-De-Prueba-4', PASSWORD_BCRYPT, ['cost' => 10]), '1']);
$pdo->exec("INSERT INTO comentarios (usuario, usuario_id, comentario, id_restaurante) VALUES ('Beto Prueba', 2, 'Comentario de Beto', 1)");

// Usuario con MFA activada (segundo factor TOTP).
require_once $webroot . '/setup/setup.php';          // funciones totp_* para las pruebas
$mfaSecret = totp_generar_secreto();
$insMfa = $pdo->prepare('INSERT INTO usuarios (nombre, email, password, estado, totp_secret) VALUES (?,?,?,?,?)');
$insMfa->execute(['Mfa Prueba', 'mfa@example.com', password_hash('Clave-Larga-De-Prueba-5', PASSWORD_BCRYPT, ['cost' => 10]), '1', $mfaSecret]);
$codigoMfaValido = fn() => totp_codigo(base32_decode($mfaSecret), (int) floor(time() / 30));

// ---------- Servidor local ----------
$puerto = 18000 + random_int(0, 999);
$env = [
    'APP_ENV' => 'prod', 'DB_DSN' => 'sqlite:' . $db, 'LOG_PATH' => $log,
    'LOG_PEPPER' => 'pepper-de-prueba', 'PNK_CONFIG_FILE' => $tmp . '/no-existe.php',
    'PATH' => getenv('PATH') ?: '/usr/bin',
];
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', $webroot], [1 => ['file', $tmp . '/srv.out', 'w'], 2 => ['file', $tmp . '/srv.err', 'w']], $pipes, $webroot, $env);
$base = "http://127.0.0.1:$puerto";
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $puerto)) break; usleep(100000); }

// ---------- Cliente HTTP mínimo con cookies ----------
final class Cliente
{
    private string $jar;
    public function __construct(private string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'jar'); }
    public function pedir(string $metodo, string $ruta, array $post = [], array $headers = []): array
    {
        $ch = curl_init($this->base . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE => $this->jar, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_TIMEOUT => 20,
        ]);
        if ($metodo === 'POST') { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
        $r = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        return ['code' => $codigo, 'headers' => substr((string) $r, 0, $hs), 'body' => substr((string) $r, $hs)];
    }
    public function get(string $ruta): array { return $this->pedir('GET', $ruta); }
    public function post(string $ruta, array $datos, array $h = []): array { return $this->pedir('POST', $ruta, $datos, $h); }
    public function csrf(int $resto = 1): string
    {
        $r = $this->get('/index.php?id=' . $resto);
        preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $r['body'], $m);
        return $m[1] ?? '';
    }
    public function cookieSesion(): string
    {
        foreach (file($this->jar) ?: [] as $l) { if (str_contains($l, 'PNKSESSID')) { return trim(substr($l, strrpos($l, "\t") + 1)); } }
        return '';
    }
    public function login(string $email, string $pass): array
    {
        $t = $this->csrf();
        return $this->post('/setup/procesalogin.php', ['csrf' => $t, 'frmusuario' => $email, 'frmpassword' => $pass]);
    }
    public function autenticado(): bool { return str_contains($this->get('/index.php?id=1')['body'], 'Bienvenido:'); }
}

$resultados = [];
function prueba(string $id, string $hallazgo, string $asvs, string $desc, callable $fn): void
{
    global $resultados;
    try { $ok = (bool) $fn(); $det = ''; } catch (Throwable $e) { $ok = false; $det = $e->getMessage(); }
    $resultados[] = [$id, $hallazgo, $asvs, $desc, $ok ? 'PASA' : 'FALLA', $det];
    printf("[%s] %-4s %-7s %s %s\n", $ok ? 'PASA ' : 'FALLA', $id, $hallazgo, $desc, $det);
}

// ================== PRUEBAS ==================

prueba('T01', 'PNK-01', '1.2.4', "Login con inyección SQL (' OR '1'='1) no autentica", function () use ($base) {
    $c = new Cliente($base);
    $c->login("' OR '1'='1' -- ", "' OR '1'='1' -- ");
    $c2 = new Cliente($base);
    $c2->login('ana@example.com', "x' OR '1'='1");
    return !$c->autenticado() && !$c2->autenticado();
});

prueba('T02', 'PNK-08', '7.2.4', 'Login válido autentica y regenera el ID de sesión', function () use ($base) {
    $c = new Cliente($base);
    $c->get('/index.php?id=1');
    $antes = $c->cookieSesion();
    $r = $c->login('ana@example.com', 'Clave-Larga-De-Prueba-1');
    return $r['code'] === 302 && $c->autenticado() && $antes !== '' && $antes !== $c->cookieSesion();
});

prueba('T03', 'PNK-08', '3.3.2/3.3.4', 'Cookie de sesión con HttpOnly y SameSite', function () use ($base) {
    $r = (new Cliente($base))->get('/index.php?id=1');
    return (bool) preg_match('/Set-Cookie: PNKSESSID=[^\r\n]*HttpOnly[^\r\n]*SameSite=Lax/i', $r['headers']);
});

prueba('T04', 'PNK-07', '3.5.1', 'Login sin token CSRF es rechazado (403)', function () use ($base) {
    $c = new Cliente($base); $c->get('/index.php?id=1');
    return $c->post('/setup/procesalogin.php', ['frmusuario' => 'ana@example.com', 'frmpassword' => 'Clave-Larga-De-Prueba-1'])['code'] === 403
        && !$c->autenticado();
});

prueba('T05', 'PNK-07', '3.5.3/7.4.1', 'Logout exige POST+CSRF e invalida la sesión', function () use ($base) {
    $c = new Cliente($base);
    $c->login('ana@example.com', 'Clave-Larga-De-Prueba-1');
    $get = $c->get('/setup/cerrar_sesion.php')['code'];
    $sigue = $c->autenticado();
    $c->post('/setup/cerrar_sesion.php', ['csrf' => $c->csrf()]);
    return $get === 405 && $sigue && !$c->autenticado();
});

prueba('T06', 'PNK-10', '7.4.2', 'Usuario con estado inactivo no puede autenticarse', function () use ($base) {
    $c = new Cliente($base); $c->login('inactivo@example.com', 'Clave-Larga-De-Prueba-3');
    return !$c->autenticado();
});

prueba('T07', 'PNK-09', '6.3.1', 'Bloqueo tras 5 intentos fallidos (incluso con la clave correcta)', function () use ($base) {
    for ($i = 0; $i < 5; $i++) { (new Cliente($base))->login('bruto@example.com', 'incorrecta' . $i); }
    $c = new Cliente($base); $c->login('bruto@example.com', 'Clave-Larga-De-Prueba-4');
    return !$c->autenticado();
});

prueba('T08', 'PNK-09', '6.3.8', 'Mensaje de error de login genérico (sin enumeración)', function () use ($base) {
    $a = new Cliente($base); $a->login('ana@example.com', 'mala'); $ra = $a->get('/index.php?id=1')['body'];
    $b = new Cliente($base); $b->login('noexiste@example.com', 'mala'); $rb = $b->get('/index.php?id=1')['body'];
    $msg = 'Usuario o contraseña incorrectos.';
    return str_contains($ra, $msg) && str_contains($rb, $msg);
});

prueba('T09', 'PNK-02', '1.2.4/2.2.1', 'index.php?id con inyección SQL devuelve 404', function () use ($base) {
    $c = new Cliente($base);
    return $c->get('/index.php?id=' . rawurlencode('1 OR 1=1'))['code'] === 404
        && $c->get('/index.php?id=' . rawurlencode('1 UNION SELECT 1,2,3,4,5,6,7,8,9'))['code'] === 404
        && $c->get('/index.php?id=1')['code'] === 200;
});

prueba('T10', 'PNK-02', '8.2.2', 'Restaurante eliminado lógicamente no es accesible', function () use ($base) {
    return (new Cliente($base))->get('/index.php?id=2')['code'] === 404;
});

prueba('T11', 'PNK-12', '1.2.1', 'XSS reflejado en id/keyid no se refleja', function () use ($base) {
    $p = rawurlencode('1"><script>alert(1)</script>');
    $c = new Cliente($base);
    $a = $c->get('/index.php?id=' . $p)['body'];
    $b = $c->get('/mostrar_carrito.php?keyid=' . $p)['body'];
    return !str_contains($a, '<script>alert(1)') && !str_contains($b, '<script>alert(1)');
});

prueba('T12', 'PNK-06', '8.2.1', 'Comentar sin autenticación es rechazado (403)', function () use ($base) {
    $c = new Cliente($base);
    return $c->post('/grcomentarios.php', ['csrf' => $c->csrf(), 'restaurante' => 1, 'comentario' => 'anon'])['code'] === 403;
});

prueba('T13', 'PNK-05', '1.2.1/3.2.2', 'Comentario con <script> se almacena y se muestra escapado', function () use ($base) {
    $c = new Cliente($base); $c->login('ana@example.com', 'Clave-Larga-De-Prueba-1');
    $c->post('/grcomentarios.php', ['csrf' => $c->csrf(), 'restaurante' => 1, 'comentario' => '<script>alert("xss")</script>']);
    $html = (new Cliente($base))->get('/index.php?id=1')['body'];
    return !str_contains($html, '<script>alert("xss")') && str_contains($html, '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
});

prueba('T14', 'PNK-06', '8.2.3', 'El autor del comentario proviene de la sesión (no del formulario)', function () use ($base, $db) {
    $c = new Cliente($base); $c->login('ana@example.com', 'Clave-Larga-De-Prueba-1');
    $c->post('/grcomentarios.php', ['csrf' => $c->csrf(), 'restaurante' => 1, 'usuario' => 'Administrador', 'comentario' => 'suplantacion']);
    $fila = (new PDO('sqlite:' . $db))->query("SELECT usuario FROM comentarios WHERE comentario='suplantacion'")->fetchColumn();
    return $fila === 'Ana Prueba';
});

prueba('T15', 'PNK-06', '8.2.2', 'Un usuario no puede eliminar el comentario de otro (IDOR)', function () use ($base, $db) {
    $c = new Cliente($base); $c->login('ana@example.com', 'Clave-Larga-De-Prueba-1');
    $id = (new PDO('sqlite:' . $db))->query("SELECT Id FROM comentarios WHERE usuario_id=2")->fetchColumn();
    $r = $c->post('/grcomentarios.php', ['csrf' => $c->csrf(), 'accion' => 'eliminar', 'restaurante' => 1, 'comentario_id' => $id]);
    $sigue = (new PDO('sqlite:' . $db))->query("SELECT COUNT(*) FROM comentarios WHERE Id=" . (int) $id)->fetchColumn();
    return $r['code'] === 403 && (int) $sigue === 1;
});

prueba('T16', 'PNK-06', '8.2.2', 'El titular sí puede eliminar su propio comentario (supresión)', function () use ($base, $db) {
    $c = new Cliente($base); $c->login('ana@example.com', 'Clave-Larga-De-Prueba-1');
    $id = (new PDO('sqlite:' . $db))->query("SELECT Id FROM comentarios WHERE comentario='suplantacion'")->fetchColumn();
    $r = $c->post('/grcomentarios.php', ['csrf' => $c->csrf(), 'accion' => 'eliminar', 'restaurante' => 1, 'comentario_id' => $id]);
    return $r['code'] === 302 && (int) (new PDO('sqlite:' . $db))->query("SELECT COUNT(*) FROM comentarios WHERE Id=" . (int) $id)->fetchColumn() === 0;
});

prueba('T17', 'PNK-07', '3.5.1/3.5.3', 'Carrito: GET=405 y POST sin CSRF=403', function () use ($base) {
    $c = new Cliente($base); $c->get('/index.php?id=1');
    return $c->get('/carrito.php')['code'] === 405 && $c->post('/carrito.php', ['op' => '1', 'iditems' => '1'])['code'] === 403;
});

prueba('T18', 'PNK-02', '1.2.4/2.2.1', 'Carrito: iditems con SQLi=400, item oculto=404, item válido=200', function () use ($base) {
    $c = new Cliente($base); $t = $c->csrf(); $h = ['X-CSRF-Token: ' . $t];
    return $c->post('/carrito.php', ['op' => '1', 'iditems' => '1 OR 1=1'], $h)['code'] === 400
        && $c->post('/carrito.php', ['op' => '1', 'iditems' => '2'], $h)['code'] === 404
        && $c->post('/carrito.php', ['op' => '1', 'iditems' => '1'], $h)['code'] === 200;
});

prueba('T19', 'PNK-11', '2.3.1', 'Vaciar carrito no cierra la sesión del usuario', function () use ($base) {
    $c = new Cliente($base); $c->login('ana@example.com', 'Clave-Larga-De-Prueba-1');
    $c->post('/carrito.php', ['op' => '3'], ['X-CSRF-Token: ' . $c->csrf()]);
    return $c->autenticado();
});

prueba('T20', 'PNK-05', '1.2.1', 'Datos de BD con HTML (nombre de item) se escapan', function () use ($base) {
    $html = (new Cliente($base))->get('/index.php?id=1')['body'];
    return str_contains($html, 'Lomo &lt;b&gt;Prueba&lt;/b&gt;') && !str_contains($html, 'Lomo <b>Prueba</b>');
});

prueba('T21', 'PNK-14', '3.4.3-3.4.6', 'Cabeceras de seguridad presentes', function () use ($base) {
    $h = (new Cliente($base))->get('/index.php?id=1')['headers'];
    foreach (['Content-Security-Policy:', "frame-ancestors 'none'", 'X-Content-Type-Options: nosniff', 'Referrer-Policy:', 'Cross-Origin-Opener-Policy: same-origin', 'Cache-Control: no-store'] as $x) {
        if (stripos($h, $x) === false) { throw new RuntimeException("falta $x"); }
    }
    return stripos($h, 'X-Powered-By') === false;
});

prueba('T22', 'PNK-15', '16.3.1/16.2.5', 'Log registra eventos de autenticación sin contraseña ni correo en claro', function () use ($log) {
    $txt = (string) @file_get_contents($log);
    return str_contains($txt, '"login_fallido"') && str_contains($txt, '"login_exitoso"') && str_contains($txt, '"csrf_invalido"')
        && !str_contains($txt, 'Clave-Larga-De-Prueba') && !str_contains($txt, 'ana@example.com');
});

prueba('T23', 'PNK-16', '16.5.1', 'Errores internos devuelven mensaje genérico (sin traza ni SQL)', function () use ($base, $db) {
    rename($db, $db . '.off');                           // fuerza fallo de BD
    touch($db); chmod($db, 0000);
    $r = (new Cliente($base))->get('/index.php?id=1');
    chmod($db, 0600); unlink($db); rename($db . '.off', $db);
    return $r['code'] === 500 && str_contains($r['body'], 'Ocurrió un error inesperado')
        && !preg_match('/SQLSTATE|PDOException|Stack trace|\.php on line/i', $r['body']);
});

prueba('T24', 'PNK-04', '13.3.1/13.2.3', 'conectar() rechaza root / contraseña vacía en MySQL', function () use ($raiz) {
    $cmd = sprintf('DB_DSN=%s DB_USER=root DB_PASS= PNK_CONFIG_FILE=/nonexistent %s -r %s 2>&1',
        escapeshellarg('mysql:host=127.0.0.1;dbname=x'), escapeshellarg(PHP_BINARY),
        escapeshellarg('require "' . $raiz . '/pnkSecurity/setup/setup.php"; try { conectar(); echo "CONECTO"; } catch (RuntimeException $e) { echo "RECHAZADO"; }'));
    return str_contains((string) shell_exec($cmd), 'RECHAZADO');
});

prueba('T25', 'PNK-18', '6.3.3', 'MFA: con contraseña correcta pero sin código, no autentica', function () use ($base) {
    $c = new Cliente($base);
    $c->login('mfa@example.com', 'Clave-Larga-De-Prueba-5');     // sin frmtotp
    return !$c->autenticado();
});

prueba('T26', 'PNK-18', '6.3.3', 'MFA: código TOTP incorrecto es rechazado', function () use ($base) {
    $c = new Cliente($base);
    $t = $c->csrf();
    $c->post('/setup/procesalogin.php', ['csrf' => $t, 'frmusuario' => 'mfa@example.com', 'frmpassword' => 'Clave-Larga-De-Prueba-5', 'frmtotp' => '000000']);
    return !$c->autenticado();
});

prueba('T27', 'PNK-18', '6.3.3', 'MFA: contraseña + código TOTP válido autentica', function () use ($base, $codigoMfaValido) {
    $c = new Cliente($base);
    $t = $c->csrf();
    $r = $c->post('/setup/procesalogin.php', ['csrf' => $t, 'frmusuario' => 'mfa@example.com', 'frmpassword' => 'Clave-Larga-De-Prueba-5', 'frmtotp' => $codigoMfaValido()]);
    return $r['code'] === 302 && $c->autenticado();
});

// ---------- Cierre ----------
proc_terminate($proc); proc_close($proc);
$total = count($resultados);
$pasan = count(array_filter($resultados, fn($r) => $r[4] === 'PASA'));
$salida = "PNK Security - Resultados de pruebas de seguridad\n"
    . 'Fecha (UTC): ' . gmdate('Y-m-d H:i:s') . "\nPHP: " . PHP_VERSION . "\nAmbiente: local aislado (php -S 127.0.0.1 + SQLite sintética)\n"
    . "Nota: el servidor embebido no aplica .htaccess; esos controles se verifican en Apache (ver docs).\n\n"
    . str_pad('ID', 5) . str_pad('Hallazgo', 9) . str_pad('ASVS v5.0.0', 14) . str_pad('Resultado', 10) . "Descripción\n";
foreach ($resultados as $r) {
    $salida .= str_pad($r[0], 5) . str_pad($r[1], 9) . str_pad($r[2], 14) . str_pad($r[4], 10) . $r[3] . ($r[5] ? " ($r[5])" : '') . "\n";
}
$salida .= "\nTotal: $pasan/$total pruebas pasan.\n";
file_put_contents(__DIR__ . '/resultados_pruebas.txt', $salida);
echo "\n$pasan/$total pruebas pasan\n";
array_map('unlink', glob($tmp . '/*') ?: []); @rmdir($tmp);
exit($pasan === $total ? 0 : 1);
