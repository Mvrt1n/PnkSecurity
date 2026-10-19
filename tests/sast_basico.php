<?php
/**
 * SAST / secret scanning básico por patrones (evidencia ASC-18). No reemplaza una herramienta
 * SAST completa (p. ej. Semgrep, SonarQube) ni la revisión manual.
 * Uso: php tests/sast_basico.php <directorio_webroot>
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$dir = $argv[1] ?? dirname(__DIR__) . '/pnkSecurity';
$reglas = [
  'SQLI-CONCAT'   => ['/(SELECT|INSERT|UPDATE|DELETE)\b[^;]*["\']\s*\.\s*\$/i', 'SQL construido por concatenación'],
  'SQLI-SUPERGL'  => ['/(SELECT|INSERT|UPDATE|DELETE)\b[^;]*\$_(GET|POST|REQUEST|COOKIE|SESSION)/i', 'Superglobal dentro de SQL'],
  'MYSQLI-QUERY'  => ['/mysqli_query\s*\(/', 'mysqli_query sin preparar'],
  'XSS-ECHO-IN'   => ['/(echo|print|<\?=)\s*\$_(GET|POST|REQUEST|COOKIE|SERVER)/', 'Salida directa de entrada del usuario'],
  'XSS-ECHO-RAW'  => ['/<\?php\s+echo\s+\$(?!_)[a-z_]+\[/i', 'echo de dato sin escapar'],
  'SECRET-DBROOT' => ['/(mysqli_connect|new\s+PDO)\s*\([^)]*["\']root["\']/i', 'Credencial root embebida'],
  'SECRET-PASS'   => ['/(password|passwd|pwd)\s*=\s*["\'][^"\']{4,}["\']/i', 'Posible contraseña embebida'],
  'PLAIN-PASS'    => ['/password\s*=\s*["\']\s*\.\s*\$_POST/i', 'Comparación de contraseña en texto plano'],
  'SESS-DESTROY'  => ['/session_destroy\(\)/', 'session_destroy (revisar contexto)'],
  'DEPRECATED'    => ['/utf8_encode\s*\(/', 'utf8_encode obsoleto en PHP 8.2+'],
];
$hall = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
  if (!preg_match('/\.(php|js)$/', $f->getFilename()) || str_contains($f->getPathname(), '/vendors/') || str_contains($f->getFilename(), '.min.')) continue;
  foreach (file($f->getPathname()) as $n => $linea) {
    foreach ($reglas as $id => [$re, $desc]) {
      if (preg_match($re, $linea)) $hall[] = sprintf('%-14s %s:%d  %s', $id, substr($f->getPathname(), strlen($dir) + 1), $n + 1, $desc);
    }
  }
}
sort($hall);
echo implode("\n", $hall), "\n\nTotal coincidencias: ", count($hall), "\n";
