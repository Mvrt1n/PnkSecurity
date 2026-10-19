<?php
/**
 * Aplica los plazos de retención definidos (docs/06_Privacidad_Ley19628_21719.md).
 * Programar diariamente (cron / Programador de tareas): php herramientas/purgar_retencion.php
 * ISO 27001 A.8.10 / ASC-11 / ASVS v5.0.0-14.2.7
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../pnkSecurity/setup/setup.php';

$dias = (int) config('RETENCION_INTENTOS_DIAS', 30);
$limite = gmdate('Y-m-d H:i:s', time() - $dias * 86400);
$borrados = consulta('DELETE FROM login_intentos WHERE fecha < :l', [':l' => $limite])->rowCount();
log_seguridad('purga_retencion', ['tabla' => 'login_intentos', 'borrados' => $borrados, 'dias' => $dias]);
echo "login_intentos eliminados: $borrados\n";
