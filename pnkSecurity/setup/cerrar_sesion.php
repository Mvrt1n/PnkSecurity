<?php
/**
 * Cierre de sesión por POST + CSRF; invalida la sesión en el servidor y la cookie.
 * ASVS v5.0.0: 3.5.3, 7.4.1
 */
declare(strict_types=1);

require __DIR__ . '/setup.php';

iniciar_sesion_segura();
exigir_post_csrf();

$restaurante = entero_positivo($_SESSION['restaurante_id'] ?? null);
log_seguridad('logout', ['usuario_id' => $_SESSION['usuario_id'] ?? null]);
cerrar_sesion_usuario();

header('Location: ../index.php' . ($restaurante ? '?id=' . $restaurante : ''));
exit;
