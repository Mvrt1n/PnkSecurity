<?php
/**
 * dast_scanner.php — Escáner dinámico de caja negra (DAST) para PNK Security.
 *
 * Realiza pruebas ACTIVAS no destructivas contra un objetivo HTTP: inyección
 * SQL (error y booleana), XSS reflejado, CSRF en endpoints que cambian estado,
 * cabeceras de seguridad, flags de cookie, métodos HTTP, listado de directorios,
 * exposición de archivos sensibles y fuga de información en errores.
 *
 * NO sustituye a una herramienta completa (OWASP ZAP, Wapiti). Es un escáner
 * propio porque el entorno no permitió instalar una. USO SÓLO EN LABORATORIO
 * sobre un objetivo autorizado (localhost).
 *
 * Uso:  php tests/dast_scanner.php http://127.0.0.1:8082
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8082', '/');
$hallazgos = [];

function http(string $metodo, string $url, array $post = null, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*',
        CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $r = (string) curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['code' => $code, 'headers' => substr($r, 0, $hs), 'body' => substr($r, $hs), 'len' => strlen($r) - $hs];
}
function add(array &$h, string $sev, string $id, string $nombre, string $detalle): void {
    $h[] = compact('sev', 'id', 'nombre', 'detalle');
}

echo "DAST PNK Security — objetivo: $base\n";
echo "Fecha (UTC): " . gmdate('Y-m-d H:i:s') . "\n" . str_repeat('=', 64) . "\n";

// --- 1. Inyección SQL booleana en index.php?id ---
$t = http('GET', "$base/index.php?id=1%20AND%201=1");
$f = http('GET', "$base/index.php?id=1%20AND%201=2");
if ($t['code'] === 200 && $f['code'] === 200 && abs($t['len'] - $f['len']) > 500) {
    add($hallazgos, 'Crítica', 'DAST-SQLI', 'Inyección SQL (booleana) en parámetro id',
        "AND 1=1 -> {$t['len']} bytes; AND 1=2 -> {$f['len']} bytes. La respuesta cambia según la condición.");
}
// error-based
$e = http('GET', "$base/index.php?id=1'");
if (preg_match('/SQL syntax|SQLSTATE|mysqli|mysql_|PDOException|you have an error in your/i', $e['body'])) {
    add($hallazgos, 'Crítica', 'DAST-SQLI-ERR', 'Error SQL expuesto en parámetro id', 'La comilla simple produce un error de base de datos visible.');
}

// --- 2. XSS reflejado ---
$marca = 'xss' . random_int(1000, 9999) . 'z';
$pl = rawurlencode('"><b>' . $marca . '</b>');
$x1 = http('GET', "$base/mostrar_carrito.php?id=1&keyid=$pl");
$x2 = http('GET', "$base/index.php?id=$pl");
foreach (['mostrar_carrito.php?keyid' => $x1, 'index.php?id' => $x2] as $donde => $resp) {
    if (strpos($resp['body'], '<b>' . $marca . '</b>') !== false) {
        add($hallazgos, 'Alta', 'DAST-XSS', "XSS reflejado en $donde", 'La entrada se refleja sin escapar en el HTML.');
    }
}

// --- 3. Cabeceras de seguridad ---
$h = http('GET', "$base/index.php?id=1");
$faltan = [];
foreach (['Content-Security-Policy', 'X-Content-Type-Options', 'Referrer-Policy', 'X-Frame-Options'] as $cab) {
    if (stripos($h['headers'], $cab) === false) { $faltan[] = $cab; }
}
if ($faltan) { add($hallazgos, 'Media', 'DAST-HDR', 'Faltan cabeceras de seguridad', implode(', ', $faltan)); }
if (stripos($h['headers'], 'X-Powered-By') !== false) {
    add($hallazgos, 'Baja', 'DAST-BANNER', 'Divulgación de versión', 'La cabecera X-Powered-By revela la versión de PHP.');
}

// --- 4. Flags de cookie ---
if (preg_match('/Set-Cookie: ([^\r\n]+)/i', $h['headers'], $m)) {
    $ck = $m[1];
    $falt = [];
    if (stripos($ck, 'HttpOnly') === false) { $falt[] = 'HttpOnly'; }
    if (stripos($ck, 'SameSite') === false) { $falt[] = 'SameSite'; }
    if ($falt) { add($hallazgos, 'Media', 'DAST-COOKIE', 'Cookie de sesión sin flags', implode(', ', $falt) . " (cookie: " . explode(';', $ck)[0] . ")"); }
}

// --- 5. CSRF en endpoint que cambia estado ---
$c = http('POST', "$base/carrito.php", ['op' => '1', 'iditems' => '1']);
if ($c['code'] < 400) {
    add($hallazgos, 'Media', 'DAST-CSRF', 'Endpoint de estado sin protección CSRF', "POST a carrito.php sin token devolvió HTTP {$c['code']}.");
}

// --- 6. Métodos HTTP peligrosos ---
$tr = http('TRACE', "$base/index.php?id=1");
if ($tr['code'] === 200) { add($hallazgos, 'Baja', 'DAST-TRACE', 'Método TRACE habilitado', 'El servidor responde a TRACE (riesgo de Cross-Site Tracing).'); }

// --- 7. Archivos sensibles / listado de directorios ---
foreach (['Script_BD/pnk_security.sql', '.git/config', 'setup/setup.php', 'config/config.local.php'] as $ruta) {
    $r = http('GET', "$base/$ruta");
    if ($r['code'] === 200 && $r['len'] > 0 && stripos($r['body'], '<?php') !== false) {
        add($hallazgos, 'Alta', 'DAST-FILE', "Archivo sensible accesible: /$ruta", "HTTP 200, {$r['len']} bytes con código fuente.");
    } elseif ($r['code'] === 200 && preg_match('/CREATE TABLE|INSERT INTO/i', $r['body'])) {
        add($hallazgos, 'Alta', 'DAST-FILE', "Volcado SQL accesible: /$ruta", 'Contiene estructura/datos de la base.');
    }
}
$d = http('GET', "$base/vendors/");
if ($d['code'] === 200 && preg_match('/Index of|Directory listing/i', $d['body'])) {
    add($hallazgos, 'Baja', 'DAST-DIR', 'Listado de directorio habilitado', '/vendors/ muestra el índice de archivos.');
}

// --- 8. Fuga de información en errores ---
$err = http('GET', "$base/index.php?id=9999999999999999999");
if (preg_match('/Fatal error|Warning:|Stack trace|\.php on line|SQLSTATE/i', $err['body'])) {
    add($hallazgos, 'Baja', 'DAST-ERR', 'Fuga de información en errores', 'La página muestra avisos/errores con detalles internos.');
}

// --- Reporte ---
$orden = ['Crítica' => 0, 'Alta' => 1, 'Media' => 2, 'Baja' => 3];
usort($hallazgos, fn($a, $b) => $orden[$a['sev']] <=> $orden[$b['sev']]);
if (!$hallazgos) {
    echo "\nSin hallazgos en las pruebas activas ejecutadas.\n";
} else {
    foreach ($hallazgos as $x) {
        printf("[%-8s] %-14s %s\n              %s\n", $x['sev'], $x['id'], $x['nombre'], $x['detalle']);
    }
}
$cont = array_count_values(array_column($hallazgos, 'sev'));
echo "\n" . str_repeat('-', 64) . "\n";
echo "Resumen: " . ($cont['Crítica'] ?? 0) . " críticas, " . ($cont['Alta'] ?? 0) . " altas, "
   . ($cont['Media'] ?? 0) . " medias, " . ($cont['Baja'] ?? 0) . " bajas.\n";
echo "Pruebas: SQLi, XSS reflejado, cabeceras, cookies, CSRF, métodos HTTP, archivos sensibles, errores.\n";
