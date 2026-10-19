<?php
/**
 * Alta y eliminación de comentarios.
 * Corrige: SQLi (PNK-02), comentario sin autenticación (PNK-06), suplantación del autor,
 * CSRF (PNK-07). La salida se escapa en index.php (PNK-05).
 * ASVS v5.0.0: 1.2.4, 2.2.1, 3.5.1, 8.2.1, 8.2.2, 8.3.1, 16.3.2
 */
declare(strict_types=1);

require __DIR__ . '/setup/setup.php';

iniciar_sesion_segura();
exigir_post_csrf();

$restaurante = entero_positivo($_POST['restaurante'] ?? null);
$volver = 'index.php' . ($restaurante ? '?id=' . $restaurante : '');

if (!usuario_autenticado()) {
    log_seguridad('authz_denegada', ['accion' => 'comentar']);
    http_response_code(403);
    exit('Debe iniciar sesión para comentar.');
}

$accion = $_POST['accion'] ?? 'crear';

if ($accion === 'eliminar') {
    // Derecho de supresión del titular sobre su propio comentario (control de propiedad, anti-IDOR).
    $idComentario = entero_positivo($_POST['comentario_id'] ?? null);
    $borrados = $idComentario === null ? 0 : consulta(
        'DELETE FROM comentarios WHERE Id = :id AND usuario_id = :u',
        [':id' => $idComentario, ':u' => $_SESSION['usuario_id']]
    )->rowCount();
    if ($borrados !== 1) {
        log_seguridad('authz_denegada', ['accion' => 'eliminar_comentario', 'comentario_id' => $idComentario]);
        http_response_code(403);
        exit('No autorizado.');
    }
    log_seguridad('comentario_eliminado', ['comentario_id' => $idComentario]);
    header('Location: ' . $volver);
    exit;
}

$comentario = texto_param($_POST, 'comentario', 1000);

$existe = $restaurante !== null && consulta(
    'SELECT 1 FROM restautantes WHERE id = :id AND eliminado IS NULL',
    [':id' => $restaurante]
)->fetchColumn();

if ($comentario === null || !$existe) {
    log_seguridad('validacion_fallida', ['accion' => 'comentar', 'restaurante' => $restaurante]);
    http_response_code(400);
    exit('Datos inválidos.');
}

// El autor se toma de la sesión, nunca del formulario.
consulta(
    'INSERT INTO comentarios (usuario, usuario_id, comentario, id_restaurante, creado) VALUES (:n, :u, :c, :r, :f)',
    [
        ':n' => $_SESSION['nombre'],
        ':u' => $_SESSION['usuario_id'],
        ':c' => $comentario,
        ':r' => $restaurante,
        ':f' => gmdate('Y-m-d H:i:s'),
    ]
);

header('Location: ' . $volver);
exit;
