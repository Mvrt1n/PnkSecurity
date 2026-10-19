<?php
/**
 * API del carrito (AJAX). Responde JSON.
 * Corrige: SQLi en iditems (PNK-02), CSRF (PNK-07), "eliminar todo" que destruía la sesión
 * de autenticación, items inexistentes/ocultos agregables, crecimiento ilimitado.
 * ASVS v5.0.0: 1.2.4, 2.2.1, 2.3.2, 2.4.1, 3.5.1, 4.1.1
 */
declare(strict_types=1);

require __DIR__ . '/setup/setup.php';

iniciar_sesion_segura();
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
exigir_post_csrf();

const MAX_ITEMS_CARRITO = 50;

function responder(int $codigo, array $cuerpo): never
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

switch ($_POST['op'] ?? '') {
    case '1': // agregar
        $id = entero_positivo($_POST['iditems'] ?? null);
        if ($id === null) {
            responder(400, ['ok' => false, 'error' => 'Producto inválido']);
        }
        if (count($_SESSION['carrito']) >= MAX_ITEMS_CARRITO) {
            responder(409, ['ok' => false, 'error' => 'Carrito lleno']);
        }
        $item = consulta(
            'SELECT id, nombre, precio FROM items WHERE id = :id AND visible = 1 AND eliminado IS NULL',
            [':id' => $id]
        )->fetch();
        if (!$item) {
            responder(404, ['ok' => false, 'error' => 'Producto no disponible']);
        }
        $pos = bin2hex(random_bytes(8));
        $_SESSION['carrito'][$pos] = [
            'posicion' => $pos,
            'id'       => (int) $item['id'],
            'nombre'   => (string) $item['nombre'],
            'precio'   => (int) $item['precio'],   // el precio siempre proviene del servidor
        ];
        responder(200, ['ok' => true]);

    case '2': // eliminar un item
        $pos = $_POST['pos'] ?? '';
        if (!is_string($pos) || !preg_match('/^[a-f0-9]{16}$/', $pos) || !isset($_SESSION['carrito'][$pos])) {
            responder(404, ['ok' => false, 'error' => 'Item no encontrado']);
        }
        unset($_SESSION['carrito'][$pos]);
        responder(200, ['ok' => true]);

    case '3': // vaciar carrito (sin cerrar la sesión del usuario)
        $_SESSION['carrito'] = [];
        responder(200, ['ok' => true]);

    default:
        responder(400, ['ok' => false, 'error' => 'Operación inválida']);
}
